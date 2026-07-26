<?php
/**
 * Hermes Agent — Domain Management AJAX endpoint
 * Actions: add_domain | verify_domain | remove_domain
 */

define('WHMCS', true);
$whmcsRoot = dirname(dirname(dirname(__DIR__)));
require $whmcsRoot . '/init.php';

use Illuminate\Database\Capsule\Manager as Capsule;

header('Content-Type: application/json');

// ─── Auth ────────────────────────────────────────────────────────────────────

$serviceId = (int)($_POST['serviceId'] ?? $_GET['serviceId'] ?? 0);
if (!$serviceId) { echo json_encode(['success' => false, 'error' => 'Missing serviceId']); exit; }

$uid = $_SESSION['uid'] ?? 0;
if (!$uid) { echo json_encode(['success' => false, 'error' => 'Not logged in']); exit; }

$svc = Capsule::table('tblhosting')->where('id', $serviceId)->where('userid', $uid)->first();
if (!$svc) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ─── Server credentials (only load if needed) ─────────────────────────────────

$server = Capsule::table('tblservers')->where('id', $svc->server)->first();
$serverParams = [];
if ($server) {
    $serverParams = [
        'serverip'         => $server->ipaddress,
        'serverhostname'   => $server->hostname,
        'serverport'       => $server->port ?: 22,
        'serverusername'   => $server->username,
        'serverpassword'   => function_exists('decrypt') ? decrypt($server->password) : '',
        'serveraccesshash' => $server->accesshash,
    ];
} else if (!in_array($action, ['save_onboarding', 'provision_status'])) {
    echo json_encode(['success' => false, 'error' => 'Server config not found']); 
    exit;
}

if ($action === 'save_onboarding') {
    try {
        $onb = Capsule::table('mod_hermesagent_onboarding')->where('serviceid', $serviceId)->first();
        if (!$onb) { echo json_encode(['success' => false, 'error' => 'Onboarding record not found.']); exit; }
        if ($onb->status !== 'pending') { echo json_encode(['success' => false, 'error' => 'Already onboarded.']); exit; }
        
        $updateData = [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($_POST['agent_name'])) $updateData['agent_name'] = trim($_POST['agent_name']);
        if (!empty($_POST['use_case'])) $updateData['use_case'] = trim($_POST['use_case']);
        if (!empty($_POST['tone'])) $updateData['tone'] = trim($_POST['tone']);
        if (!empty($_POST['custom_instructions'])) $updateData['custom_instructions'] = trim($_POST['custom_instructions']);
        
        if (!empty($_POST['skip'])) $updateData['status'] = 'skipped';
        
        Capsule::table('mod_hermesagent_onboarding')->where('serviceid', $serviceId)->update($updateData);
        
        // Pass the first admin username to localAPI
        $admin = Capsule::table('tbladmins')->where('disabled', 0)->first();
        $adminUser = $admin ? $admin->username : 'admin';
        
        $results = localAPI('ModuleCreate', ['accountid' => $serviceId], $adminUser);
        
        if ($results['result'] === 'error') {
            echo json_encode(['success' => false, 'error' => 'ModuleCreate API Error: ' . $results['message']]);
            exit;
        }
        
        echo json_encode(['success' => true, 'api_result' => $results]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Server Exception: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'provision_status') {
    $instance = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceId)->first();
    echo json_encode(['success' => true, 'provisioned' => ($instance && $instance->status === 'Active')]);
    exit;
}

// ─── Instance record (required for domain actions) ───────────────────────────

$instance = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceId)->first();
if (!$instance) { echo json_encode(['success' => false, 'error' => 'Instance not found']); exit; }

$hostPort = $instance->host_port ?: (7300 + ($serviceId % 1000));
$dashPort = $instance->dash_port;
$serverIp = '46.62.205.66';

// ─── Load module functions ────────────────────────────────────────────────────

require_once __DIR__ . '/hermesagent.php';

if ($action === 'add_domain') {
    $domain = strtolower(trim($_POST['domain'] ?? ''));
    $type   = ($_POST['type'] ?? '') === 'custom' ? 'custom' : 'hermes';

    if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $domain)) {
        echo json_encode(['success' => false, 'error' => 'Invalid domain format']); exit;
    }
    if (Capsule::table('mod_hermesagent_domains')->where('domain', $domain)->exists()) {
        echo json_encode(['success' => false, 'error' => 'Domain already in use']); exit;
    }

    if ($type === 'hermes') {
        if (!preg_match('/^[a-z0-9\-]+\.hermes\.deltadns\.xyz$/', $domain)) {
            echo json_encode(['success' => false, 'error' => 'Hermes subdomains must end in .hermes.deltadns.xyz']); exit;
        }
        try {
            $ssh = hermesagent_get_ssh_client($serverParams, 15);
            hermesagent_domain_write_caddy($ssh, $serviceId, $domain, $hostPort, $dashPort);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'SSH failed: ' . $e->getMessage()]); exit;
        }
        Capsule::table('mod_hermesagent_domains')->insert([
            'service_id' => $serviceId, 'domain' => $domain,
            'type' => 'hermes', 'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true, 'status' => 'active', 'domain' => $domain]);

    } else {
        Capsule::table('mod_hermesagent_domains')->insert([
            'service_id' => $serviceId, 'domain' => $domain,
            'type' => 'custom', 'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true, 'status' => 'pending', 'domain' => $domain, 'a_record' => $serverIp]);
    }
    exit;
}

if ($action === 'verify_domain') {
    $domain = strtolower(trim($_POST['domain'] ?? ''));
    $row = Capsule::table('mod_hermesagent_domains')
        ->where('domain', $domain)->where('service_id', $serviceId)->first();
    if (!$row) { echo json_encode(['success' => false, 'error' => 'Domain not found']); exit; }

    $records  = @dns_get_record($domain, DNS_A);
    $resolved = false;
    $foundIp  = '';
    foreach ((array)$records as $r) {
        if (($r['ip'] ?? '') === $serverIp) { $resolved = true; break; }
        $foundIp = $r['ip'] ?? '';
    }
    if (!$resolved) {
        $hint = $foundIp ? " (currently points to {$foundIp})" : " (no A record found yet)";
        echo json_encode(['success' => false, 'error' => "DNS not yet pointing to {$serverIp}{$hint}"]); exit;
    }

    try {
        $ssh = hermesagent_get_ssh_client($serverParams, 15);
        hermesagent_domain_write_caddy($ssh, $serviceId, $domain, $hostPort, $dashPort);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'SSH failed: ' . $e->getMessage()]); exit;
    }
    Capsule::table('mod_hermesagent_domains')
        ->where('domain', $domain)->where('service_id', $serviceId)
        ->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
    echo json_encode(['success' => true, 'status' => 'active']);
    exit;
}

if ($action === 'remove_domain') {
    $domainId = (int)($_POST['domain_id'] ?? 0);
    $row = Capsule::table('mod_hermesagent_domains')
        ->where('id', $domainId)->where('service_id', $serviceId)->first();
    if (!$row) { echo json_encode(['success' => false, 'error' => 'Domain not found']); exit; }
    if ($row->domain === $serviceId . '.hermes.deltadns.xyz') {
        echo json_encode(['success' => false, 'error' => 'Cannot remove the default domain']); exit;
    }
    if ($row->status === 'active') {
        try {
            $ssh = hermesagent_get_ssh_client($serverParams, 15);
            hermesagent_domain_remove_caddy($ssh, $row->domain);
        } catch (\Exception $e) { /* log silently */ }
    }
    Capsule::table('mod_hermesagent_domains')->where('id', $domainId)->delete();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'approve_pairing') {
    $platform = trim($_POST['platform'] ?? 'telegram');
    $code     = preg_replace('/[^A-Z0-9]/i', '', strtoupper(trim($_POST['code'] ?? '')));

    if (empty($code)) {
        echo json_encode(['success' => false, 'error' => 'Pairing code is required']); exit;
    }

    $platformArg = strtolower($platform);

    try {
        $ssh = hermesagent_get_ssh_client($serverParams, 20);
        $cmd = "docker exec hermes-{$serviceId} hermes pairing approve {$platformArg} {$code} 2>&1 && echo 'PAIR_OK' || echo 'PAIR_FAIL'";
        $result = trim($ssh->exec($cmd));

        if (strpos($result, 'PAIR_OK') !== false || stripos($result, 'approved') !== false || stripos($result, 'success') !== false) {
            echo json_encode(['success' => true, 'output' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Pairing failed — check the code and try again.', 'output' => $result]);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'SSH error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'connect_messaging') {
    $platform = trim($_POST['platform'] ?? '');
    $token    = trim($_POST['token'] ?? '');

    $envKeys = [
        'telegram' => 'TELEGRAM_BOT_TOKEN',
        'discord'  => 'DISCORD_BOT_TOKEN',
        'slack'    => 'SLACK_BOT_TOKEN',
    ];

    if (!isset($envKeys[$platform])) {
        echo json_encode(['success' => false, 'error' => 'Invalid platform']); exit;
    }
    if ($token === '') {
        echo json_encode(['success' => false, 'error' => 'Token is required']); exit;
    }

    // Step 1: Validate token against the platform API
    $botName = '';
    if ($platform === 'telegram') {
        $ch = curl_init("https://api.telegram.org/bot{$token}/getMe");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        if ($code !== 200 || empty($data['ok'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid Telegram token — Telegram API rejected it.']); exit;
        }
        $botName = '@' . ($data['result']['username'] ?? 'Unknown');
    } elseif ($platform === 'discord') {
        $ch = curl_init("https://discord.com/api/v10/users/@me");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Authorization: Bot {$token}"],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        if ($code !== 200 || empty($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid Discord token — authentication failed.']); exit;
        }
        $botName = ($data['username'] ?? 'Unknown') . '#' . ($data['discriminator'] ?? '0');
    } elseif ($platform === 'slack') {
        $ch = curl_init("https://slack.com/api/auth.test");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        if ($code !== 200 || empty($data['ok'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid Slack token — authentication failed.']); exit;
        }
        $botName = $data['user'] ?? $data['bot_id'] ?? 'Slack Bot';
    }

    // Step 2: SSH into container and inject token
    $envKey  = $envKeys[$platform];
    $dataDir = "/srv/hermes/{$serviceId}/data";
    $safeToken = escapeshellarg($token);

    try {
        $ssh = hermesagent_get_ssh_client($serverParams, 20);
        $cmd  = "sed -i '/^{$envKey}=/d' \"{$dataDir}/.env\"\n";
        $cmd .= "echo \"{$envKey}={$safeToken}\" >> \"{$dataDir}/.env\"\n";
        $cmd .= "docker restart \"hermes-{$serviceId}\" >/dev/null 2>&1 && echo 'RESTARTED' || echo 'RESTART_FAILED'\n";
        $result = trim($ssh->exec($cmd));

        if (strpos($result, 'RESTARTED') === false) {
            echo json_encode(['success' => false, 'error' => 'Token validated but container restart failed.']); exit;
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'SSH error: ' . $e->getMessage()]); exit;
    }

    echo json_encode(['success' => true, 'bot_name' => $botName, 'platform' => $platform]);
    exit;
}

if ($action === 'disconnect_messaging') {
    $platform = trim($_POST['platform'] ?? '');
    $envKeys  = ['telegram' => 'TELEGRAM_BOT_TOKEN', 'discord' => 'DISCORD_BOT_TOKEN', 'slack' => 'SLACK_BOT_TOKEN'];

    if (!isset($envKeys[$platform])) {
        echo json_encode(['success' => false, 'error' => 'Invalid platform']); exit;
    }

    $envKey  = $envKeys[$platform];
    $dataDir = "/srv/hermes/{$serviceId}/data";

    try {
        $ssh = hermesagent_get_ssh_client($serverParams, 20);
        $cmd  = "sed -i '/^{$envKey}=/d' \"{$dataDir}/.env\"\n";
        $cmd .= "docker restart \"hermes-{$serviceId}\" >/dev/null 2>&1 && echo 'RESTARTED' || echo 'RESTART_FAILED'\n";
        $result = trim($ssh->exec($cmd));

        if (strpos($result, 'RESTARTED') === false) {
            echo json_encode(['success' => false, 'error' => 'Container restart failed.']); exit;
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'SSH error: ' . $e->getMessage()]); exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
