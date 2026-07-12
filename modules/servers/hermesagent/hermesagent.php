<?php
/**
 * Hermes Agent Hosting Provisioning Module
 *
 * @copyright Copyright (c) snbdhost 2026
 * @license MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Define module metadata.
 */
function hermesagent_MetaData() {
    return [
        'DisplayName' => 'Hermes Agent Hosting',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '22',
        'DefaultSSLPort' => '22',
        'ServiceSingleSignOnLabel' => 'Open Hermes Dashboard',
        'AdminSingleSignOnLabel' => 'Open Hermes Dashboard (Admin)',
        'ListAccountsUniqueIdentifierDisplayName' => 'Domain',
        'ListAccountsUniqueIdentifierField' => 'domain',
        'ListAccountsProductField' => 'configoption1',
    ];
}

/**
 * Define product configuration options.
 */
function hermesagent_ConfigOptions() {
    return [
        'llm_provider' => [
            'FriendlyName' => 'LLM Provider',
            'Type' => 'dropdown',
            'Options' => [
                'nous_portal' => 'Nous Portal (recommended — models + tools in one)',
                'openrouter'  => 'OpenRouter',
                'openai'      => 'OpenAI',
                'anthropic'   => 'Anthropic',
                'custom'      => 'Custom OpenAI-compatible endpoint',
            ],
            'Default' => 'nous_portal',
            'Description' => 'Which inference provider Hermes should use.',
        ],
        'provider_api_key' => [
            'FriendlyName' => 'Provider API Key',
            'Type' => 'password',
            'Size' => '48',
            'Description' => 'API key/token for the selected provider (client-supplied, stored encrypted).',
        ],
        'custom_endpoint_url' => [
            'FriendlyName' => 'Custom Endpoint URL',
            'Type' => 'text',
            'Size' => '48',
            'Description' => 'Only used when "Custom OpenAI-compatible endpoint" is selected.',
        ],
        'model_name' => [
            'FriendlyName' => 'Model',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'hermes-4-405b',
            'Description' => 'Model identifier to configure Hermes with.',
        ],
        'messaging_platform' => [
            'FriendlyName' => 'Messaging Platform',
            'Type' => 'dropdown',
            'Options' => 'None,Telegram,Discord,Slack',
            'Default' => 'None',
            'Description' => 'Optional: chat with Hermes from a messaging platform in addition to the dashboard.',
        ],
        'messaging_token' => [
            'FriendlyName' => 'Bot Token',
            'Type' => 'password',
            'Size' => '48',
            'Description' => 'Bot token for the selected messaging platform (leave blank if None).',
        ],
        'dashboard_username' => [
            'FriendlyName' => 'Dashboard Username',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'admin',
            'Description' => 'Login username for the Hermes web dashboard / Desktop Remote Gateway.',
        ],
        'enable_api_server' => [
            'FriendlyName' => 'Enable OpenAI-Compatible API',
            'Type' => 'yesno',
            'Description' => 'Expose /v1/chat/completions for third-party frontends (Open WebUI, etc.).',
        ],
        'resource_tier' => [
            'FriendlyName' => 'Resource Tier',
            'Type' => 'dropdown',
            'Options' => 'Starter (1 vCPU / 1GB),Standard (2 vCPU / 2GB),Pro (4 vCPU / 4GB)',
            'Default' => 'Standard (2 vCPU / 2GB)',
            'Description' => 'Container CPU/memory limits.',
        ],
        'docker_image_tag' => [
            'FriendlyName' => 'Image Version',
            'Type' => 'text',
            'Size' => '15',
            'Default' => 'latest',
            'Description' => 'nousresearch/hermes-agent tag to deploy. Leave as latest unless pinning.',
        ],
    ];
}

/**
 * Setup custom database table for storing service credentials and port mappings.
 */
function hermesagent_setup_database() {
    try {
        if (!Capsule::schema()->hasTable('mod_hermesagent_instances')) {
            Capsule::schema()->create(
                'mod_hermesagent_instances',
                function ($table) {
                    $table->integer('serviceid')->unique();
                    $table->integer('dash_port');
                    $table->integer('api_port');
                    $table->string('dashboard_username');
                    $table->string('dashboard_password');
                    $table->string('dashboard_secret');
                    $table->string('api_key');
                    $table->string('status')->default('Pending');
                    $table->timestamps();
                }
            );
        }
    } catch (\Exception $e) {
        logActivity("HermesAgent database setup failed: " . $e->getMessage());
    }
}

/**
 * Allocate dashboard and API ports deterministically with collision detection.
 */
function hermesagent_allocate_ports($serviceid) {
    $dashBase = 9119;
    $apiBase = 8642;
    
    $dashPort = $dashBase + ($serviceid % 1000);
    $apiPort = $apiBase + ($serviceid % 1000);
    
    $attempts = 0;
    while ($attempts < 500) {
        $exists = Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', '!=', $serviceid)
            ->where(function($query) use ($dashPort, $apiPort) {
                $query->where('dash_port', $dashPort)
                      ->orWhere('api_port', $apiPort)
                      ->orWhere('dash_port', $apiPort)
                      ->orWhere('api_port', $dashPort);
            })->exists();
            
        if (!$exists) {
            return [$dashPort, $apiPort];
        }
        
        $dashPort++;
        $apiPort++;
        $attempts++;
    }
    
    return [rand(10000, 20000), rand(20001, 30000)];
}

/**
 * Helper to generate a secure random password.
 */
function hermesagent_generate_random_password($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

/**
 * Resolves configuration parameters with case-insensitive checks against customer-defined 
 * Configurable Options and Custom Fields in WHMCS. Falls back to admin default settings.
 */
function hermesagent_resolve_param($params, $configKey, $name, $defaultVal = '') {
    $sanitize = function($str) {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    };
    
    $search = $sanitize($name);
    
    // 1. Search in Customer Configurable Options
    if (!empty($params['configoptions'])) {
        foreach ($params['configoptions'] as $key => $val) {
            if ($sanitize($key) === $search) {
                return $val;
            }
        }
    }
    
    // 2. Search in Customer Custom Fields
    if (!empty($params['customfields'])) {
        foreach ($params['customfields'] as $key => $val) {
            if ($sanitize($key) === $search) {
                return $val;
            }
        }
    }
    
    // 3. Fallback to Admin Module Config Options
    if (isset($params[$configKey])) {
        return $params[$configKey];
    }
    
    return $defaultVal;
}

/**
 * Establish SSH connection using phpseclib compatibility layer.
 */
function hermesagent_get_ssh_client($params) {
    $host = $params['serverip'];
    $port = empty($params['serverport']) ? 22 : intval($params['serverport']);
    $username = $params['serverusername'];
    $password = $params['serverpassword'];
    $accesshash = trim($params['serveraccesshash']);

    if (empty($host) || empty($username)) {
        throw new \Exception("Server IP/Username not configured in WHMCS Server settings");
    }

    // Try phpseclib 3
    if (class_exists('\phpseclib3\Net\SSH2')) {
        $ssh = new \phpseclib3\Net\SSH2($host, $port);
        $ssh->setTimeout(30);
        if (!empty($accesshash)) {
            try {
                $key = \phpseclib3\Crypt\PublicKeyLoader::load($accesshash, $password ?: false);
                if ($ssh->login($username, $key)) {
                    return $ssh;
                }
            } catch (\Exception $e) {
                // fall back to password
            }
        }
        if ($ssh->login($username, $password)) {
            return $ssh;
        }
        throw new \Exception("SSH connection failed (phpseclib3)");
    } 
    // Try phpseclib 2
    elseif (class_exists('\phpseclib\Net\SSH2')) {
        $ssh = new \phpseclib\Net\SSH2($host, $port);
        $ssh->setTimeout(30);
        if (!empty($accesshash) && class_exists('\phpseclib\Crypt\RSA')) {
            $key = new \phpseclib\Crypt\RSA();
            if (!empty($password)) {
                $key->setPassword($password);
            }
            if ($key->loadKey($accesshash)) {
                if ($ssh->login($username, $key)) {
                    return $ssh;
                }
            }
        }
        if ($ssh->login($username, $password)) {
            return $ssh;
        }
        throw new \Exception("SSH connection failed (phpseclib2)");
    }
    // Try legacy phpseclib 1
    elseif (class_exists('Net_SSH2')) {
        $ssh = new \Net_SSH2($host, $port);
        $ssh->setTimeout(30);
        if (!empty($accesshash) && class_exists('Crypt_RSA')) {
            $key = new \Crypt_RSA();
            if (!empty($password)) {
                $key->setPassword($password);
            }
            if ($key->loadKey($accesshash)) {
                if ($ssh->login($username, $key)) {
                    return $ssh;
                }
            }
        }
        if ($ssh->login($username, $password)) {
            return $ssh;
        }
        throw new \Exception("SSH connection failed (legacy Net_SSH2)");
    }

    throw new \Exception("phpseclib classes not found. Please ensure phpseclib is available.");
}

/**
 * Core Account Provisioning
 */
function hermesagent_CreateAccount($params) {
    hermesagent_setup_database();
    
    $serviceid = intval($params['serviceid']);
    
    // Resolve values with customer custom inputs and configurable option overrides
    $llmProvider = hermesagent_resolve_param($params, 'configoption1', 'LLM Provider', 'nous_portal');
    $providerApiKey = hermesagent_resolve_param($params, 'configoption2', 'Provider API Key', '');
    $customEndpointUrl = hermesagent_resolve_param($params, 'configoption3', 'Custom Endpoint URL', '');
    $modelName = hermesagent_resolve_param($params, 'configoption4', 'Model', 'hermes-4-405b');
    $messagingPlatform = hermesagent_resolve_param($params, 'configoption5', 'Messaging Platform', 'None');
    $messagingToken = hermesagent_resolve_param($params, 'configoption6', 'Bot Token', '');
    $dashboardUsername = hermesagent_resolve_param($params, 'configoption7', 'Dashboard Username', 'admin');
    $enableApiServer = hermesagent_resolve_param($params, 'configoption8', 'Enable OpenAI-Compatible API', 'no');
    $resourceTier = hermesagent_resolve_param($params, 'configoption9', 'Resource Tier', 'Standard (2 vCPU / 2GB)');
    $dockerImageTag = hermesagent_resolve_param($params, 'configoption10', 'Image Version', 'latest');
    
    // Check if record exists
    $record = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceid)->first();
    if ($record) {
        $dashPort = $record->dash_port;
        $apiPort = $record->api_port;
        $dashboardPassword = $record->dashboard_password;
        $dashboardSecret = $record->dashboard_secret ?: bin2hex(random_bytes(16));
        $apiKey = $record->api_key;
    } else {
        list($dashPort, $apiPort) = hermesagent_allocate_ports($serviceid);
        $dashboardPassword = hermesagent_generate_random_password(16);
        $dashboardSecret = bin2hex(random_bytes(16));
        $apiKey = bin2hex(random_bytes(16));
        
        Capsule::table('mod_hermesagent_instances')->insert([
            'serviceid' => $serviceid,
            'dash_port' => $dashPort,
            'api_port' => $apiPort,
            'dashboard_username' => $dashboardUsername,
            'dashboard_password' => $dashboardPassword,
            'dashboard_secret' => $dashboardSecret,
            'api_key' => $apiKey,
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        // Define resource tier limits
        $cpus = '2.0';
        $memory = '2g';
        if (stripos($resourceTier, 'Starter') !== false) {
            $cpus = '1.0';
            $memory = '1g';
        } elseif (stripos($resourceTier, 'Pro') !== false) {
            $cpus = '4.0';
            $memory = '4g';
        }
        
        // Generate Env
        $envLines = [
            "# Generated by WHMCS hermesagent module",
            "HERMES_DASHBOARD=1",
            "HERMES_DASHBOARD_HOST=0.0.0.0",
            "HERMES_DASHBOARD_PORT=9119",
            "HERMES_DASHBOARD_BASIC_AUTH_USERNAME=" . $dashboardUsername,
            "HERMES_DASHBOARD_BASIC_AUTH_PASSWORD=" . $dashboardPassword,
            "HERMES_DASHBOARD_BASIC_AUTH_SECRET=" . $dashboardSecret
        ];
        
        if ($llmProvider === 'openrouter') {
            $envLines[] = "OPENROUTER_API_KEY=" . $providerApiKey;
        } elseif ($llmProvider === 'openai') {
            $envLines[] = "OPENAI_API_KEY=" . $providerApiKey;
        } elseif ($llmProvider === 'anthropic') {
            $envLines[] = "ANTHROPIC_API_KEY=" . $providerApiKey;
        } elseif ($llmProvider === 'custom') {
            $envLines[] = "OPENAI_API_KEY=" . $providerApiKey;
            $envLines[] = "OPENAI_API_BASE=" . $customEndpointUrl;
        } elseif ($llmProvider === 'nous_portal' && !empty($providerApiKey)) {
            $envLines[] = "NOUS_PORTAL_API_KEY=" . $providerApiKey;
        }
        
        $apiEnabledVal = (strtolower($enableApiServer) === 'yes' || $enableApiServer === '1' || $enableApiServer === true || $enableApiServer === 'on');
        if ($apiEnabledVal) {
            $envLines[] = "API_SERVER_ENABLED=true";
            $envLines[] = "API_SERVER_HOST=0.0.0.0";
            $envLines[] = "API_SERVER_PORT=8642";
            $envLines[] = "API_SERVER_KEY=" . $apiKey;
            $envLines[] = "API_SERVER_CORS_ORIGINS=*";
        } else {
            $envLines[] = "API_SERVER_ENABLED=false";
        }
        
        if ($messagingPlatform !== 'None' && !empty($messagingToken)) {
            if ($messagingPlatform === 'Telegram') {
                $envLines[] = "TELEGRAM_BOT_TOKEN=" . $messagingToken;
            } elseif ($messagingPlatform === 'Discord') {
                $envLines[] = "DISCORD_BOT_TOKEN=" . $messagingToken;
            } elseif ($messagingPlatform === 'Slack') {
                $envLines[] = "SLACK_BOT_TOKEN=" . $messagingToken;
            }
        }
        
        $envContent = implode("\n", $envLines) . "\n";
        
        // Generate config.yaml
        $yamlContent = <<<YAML
model: "{$llmProvider}/{$modelName}"
tool_loop_guardrails:
  warnings_enabled: true
  hard_stop_enabled: true
  hard_stop_after:
    exact_failure: 5
    idempotent_no_progress: 5
terminal:
  backend: docker
YAML;

        // Build installation commands
        $dataDir = "/srv/hermes/{$serviceid}/data";
        $setupCmds = "mkdir -p \"{$dataDir}\"\n";
        
        // Write .env
        $setupCmds .= "cat << 'EOF' > \"{$dataDir}/.env\"\n{$envContent}EOF\n";
        $setupCmds .= "chmod 600 \"{$dataDir}/.env\"\n";
        
        // Write config.yaml
        $setupCmds .= "cat << 'EOF' > \"{$dataDir}/config.yaml\"\n{$yamlContent}\nEOF\n";
        
        // Check if WHMCS Server requires secure connection (uses reverse proxy caddy)
        $isSecure = !empty($params['serversecure']) && ($params['serversecure'] === true || $params['serversecure'] === 'on' || $params['serversecure'] === '1');
        $bindIp = $isSecure ? "127.0.0.1" : "0.0.0.0";
        
        // Remove existing container
        $setupCmds .= "docker rm -f \"hermes-{$serviceid}\" 2>/dev/null || true\n";
        
        // Run container
        $setupCmds .= "docker run -d \\
  --name \"hermes-{$serviceid}\" \\
  --restart unless-stopped \\
  --cpus=\"{$cpus}\" --memory=\"{$memory}\" \\
  --env-file \"{$dataDir}/.env\" \\
  -v \"{$dataDir}:/opt/data\" \\
  -p \"{$bindIp}:{$dashPort}:9119\" \\
  -p \"{$bindIp}:{$apiPort}:8642\" \\
  nousresearch/hermes-agent:{$dockerImageTag} gateway run\n";
        
        // Inject SNBD HOST branding and GTM into the compiled web dashboard
        $brandingHtml = <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-N675SJK');</script>
<!-- End Google Tag Manager -->
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-N675SJK"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<style>
  .snbd-topbar { position: fixed; top: 0; left: 0; right: 0; background: #CC0000; color: white; text-align: center; padding: 10px 40px 10px 20px; font-family: 'Inter', system-ui, sans-serif; font-size: 13px; font-weight: 500; z-index: 999998; box-shadow: 0 2px 10px rgba(0,0,0,0.15); line-height: 1.4; }
  .snbd-topbar strong { font-weight: 700; background: rgba(0,0,0,0.15); padding: 3px 8px; border-radius: 4px; margin-right: 8px; }
  .snbd-topbar-close { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: white; font-size: 20px; cursor: pointer; opacity: 0.7; transition: opacity 0.2s; padding: 0 5px; }
  .snbd-topbar-close:hover { opacity: 1; }
  body { padding-top: 40px !important; }
</style>
<div class="snbd-topbar" id="snbd-topbar">
  <strong>SNBD HOST Beta Program</strong>
  This is a free tier for our beta — still in active development. As part of helping us build and improve it, we collect usage tracking data that shows us how the service is performing.
  <button class="snbd-topbar-close" onclick="document.getElementById('snbd-topbar').style.display='none'" title="Dismiss">&times;</button>
</div>
HTML;
        
        $setupCmds .= "sleep 3\n"; // wait a moment for container filesystem to be ready
        $setupCmds .= "docker exec \"hermes-{$serviceid}\" sh -c \"sed -i 's/<title>Hermes Agent - Dashboard<\\/title>/<title>{$dashboardUsername} Hermes Agent<\\/title>/g' /opt/hermes/hermes_cli/web_dist/index.html\"\n";
        
        // Write branding to host first to avoid escaping issues, then pipe to container
        $setupCmds .= "cat << 'BRANDING_EOF' > \"{$dataDir}/branding.html\"\n{$brandingHtml}\nBRANDING_EOF\n";
        $setupCmds .= "docker exec -i \"hermes-{$serviceid}\" sh -c \"cat >> /opt/hermes/hermes_cli/web_dist/index.html\" < \"{$dataDir}/branding.html\"\n";
        
        // Add Reverse Proxy config if Caddy is present and secure is active
        $hostname = "hermes.deltadns.xyz";
        $caddyConfig = <<<CADDY
hermes-{$serviceid}.{$hostname} {
    reverse_proxy 127.0.0.1:{$dashPort}
}
CADDY;
        if ($apiEnabledVal) {
            $caddyConfig .= "\nhermes-api-{$serviceid}.{$hostname} {\n    reverse_proxy 127.0.0.1:{$apiPort}\n}";
        }
        
        $setupCmds .= "if which caddy >/dev/null 2>&1; then\n";
        $setupCmds .= "  mkdir -p /etc/caddy/conf.d\n";
        $setupCmds .= "  cat << 'EOF' > \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\"\n{$caddyConfig}\nEOF\n";
        $setupCmds .= "  if ! grep -q \"import conf.d/\*.conf\" /etc/caddy/Caddyfile; then\n";
        $setupCmds .= "    echo \"import conf.d/*.conf\" >> /etc/caddy/Caddyfile\n";
        $setupCmds .= "  fi\n";
        $setupCmds .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
        $setupCmds .= "fi\n";
        
        $setupCmds .= "echo \"HEALTHY\"\n";

        // Execute commands
        $sshResponse = $ssh->exec($setupCmds);
        
        // Redact secrets in logs
        $redactedCmds = $setupCmds;
        if (!empty($providerApiKey)) {
            $redactedCmds = str_replace($providerApiKey, '[REDACTED_API_KEY]', $redactedCmds);
        }
        if (!empty($messagingToken)) {
            $redactedCmds = str_replace($messagingToken, '[REDACTED_MSG_TOKEN]', $redactedCmds);
        }
        $redactedCmds = str_replace($dashboardPassword, '[REDACTED_DASH_PASSWORD]', $redactedCmds);
        $redactedCmds = str_replace($dashboardSecret, '[REDACTED_DASH_SECRET]', $redactedCmds);
        $redactedCmds = str_replace($apiKey, '[REDACTED_API_KEY]', $redactedCmds);

        logModuleCall(
            'hermesagent',
            'CreateAccount',
            $redactedCmds,
            $sshResponse,
            null,
            [$providerApiKey, $messagingToken, $dashboardPassword, $apiKey]
        );

        if (strpos($sshResponse, 'HEALTHY') === false) {
            Capsule::table('mod_hermesagent_instances')
                ->where('serviceid', $serviceid)
                ->update(['status' => 'Error', 'updated_at' => date('Y-m-d H:i:s')]);
            return "Deployment completed but health check failed: " . strip_tags(trim($sshResponse));
        }

        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->update(['status' => 'Active', 'updated_at' => date('Y-m-d H:i:s')]);

        return "success";
    } catch (\Exception $e) {
        logModuleCall('hermesagent', 'CreateAccount_Failed', $e->getMessage(), $e->getTraceAsString());
        return "SSH Provisioning failed: " . $e->getMessage();
    }
}

/**
 * Account Suspension
 */
function hermesagent_SuspendAccount($params) {
    $serviceid = intval($params['serviceid']);
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        $hostname = "hermes.deltadns.xyz";
        $enableApiServer = hermesagent_resolve_param($params, 'configoption8', 'Enable OpenAI-Compatible API', 'no');
        $apiEnabledVal = ($enableApiServer === 'yes' || $enableApiServer === 'on' || $enableApiServer === '1');
        
        $suspendedHtml = "<!DOCTYPE html><html><head><title>Account Suspended</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f9fafb;color:#111827;} .box{text-align:center;padding:40px;background:white;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);max-width:500px;} h1{color:#dc2626;margin-top:0;} p{color:#4b5563;line-height:1.5;}</style></head><body><div class=\"box\"><h1>Account Suspended</h1><p>Your Hermes Agent account has been suspended.</p><p>Please check your billing status or contact support to reactivate your service.</p></div></body></html>";
        
        $caddySuspended = <<<CADDY
hermes-{$serviceid}.{$hostname} {
    respond `{$suspendedHtml}` 403 {
        close
    }
}
CADDY;
        if ($apiEnabledVal) {
            $caddySuspended .= "\nhermes-api-{$serviceid}.{$hostname} {\n    respond `{\"error\":\"Account Suspended\"}` 403 {\n        close\n    }\n}";
        }
        
        $cmd = "docker stop \"hermes-{$serviceid}\"";
        $cmd .= " && if [ -d \"/etc/caddy/conf.d\" ]; then\n";
        $cmd .= "  cat << 'EOF' > \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\"\n{$caddySuspended}\nEOF\n";
        $cmd .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
        $cmd .= "fi";
        
        $result = $ssh->exec($cmd);
        
        logModuleCall('hermesagent', 'SuspendAccount', $cmd, $result);
        
        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->update(['status' => 'Suspended', 'updated_at' => date('Y-m-d H:i:s')]);
            
        return "success";
    } catch (\Exception $e) {
        return "Suspension failed: " . $e->getMessage();
    }
}

/**
 * Account Unsuspension
 */
function hermesagent_UnsuspendAccount($params) {
    $serviceid = intval($params['serviceid']);
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        $record = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceid)->first();
        if (!$record) {
            return "Deployment record not found in database.";
        }
        
        $dashPort = $record->dash_port;
        $apiPort = $record->api_port;
        $hostname = "hermes.deltadns.xyz";
        
        $enableApiServer = hermesagent_resolve_param($params, 'configoption8', 'Enable OpenAI-Compatible API', 'no');
        $apiEnabledVal = ($enableApiServer === 'yes' || $enableApiServer === 'on' || $enableApiServer === '1');
        
        $caddyConfig = <<<CADDY
hermes-{$serviceid}.{$hostname} {
    reverse_proxy 127.0.0.1:{$dashPort}
}
CADDY;
        if ($apiEnabledVal) {
            $caddyConfig .= "\nhermes-api-{$serviceid}.{$hostname} {\n    reverse_proxy 127.0.0.1:{$apiPort}\n}";
        }
        
        $cmd = "docker start \"hermes-{$serviceid}\"";
        $cmd .= " && if [ -d \"/etc/caddy/conf.d\" ]; then\n";
        $cmd .= "  cat << 'EOF' > \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\"\n{$caddyConfig}\nEOF\n";
        $cmd .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
        $cmd .= "fi";
        
        $result = $ssh->exec($cmd);
        
        logModuleCall('hermesagent', 'UnsuspendAccount', $cmd, $result);
        
        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->update(['status' => 'Active', 'updated_at' => date('Y-m-d H:i:s')]);
            
        return "success";
    } catch (\Exception $e) {
        return "Unsuspension failed: " . $e->getMessage();
    }
}

/**
 * Account Termination
 */
function hermesagent_TerminateAccount($params) {
    $serviceid = intval($params['serviceid']);
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        // Steps: 
        // 1. Create a timestamped backup archive of data
        // 2. Remove container
        // 3. Remove client files
        // 4. Remove Caddy reverse proxy config if present
        $cmd = "mkdir -p /srv/hermes/archive && \\\n";
        $cmd .= "tar -czf \"/srv/hermes/archive/hermes-{$serviceid}-\$(date +%Y%m%d%H%M%S).tar.gz\" -C /srv/hermes/{$serviceid} data 2>/dev/null || true && \\\n";
        $cmd .= "docker rm -fv \"hermes-{$serviceid}\" 2>/dev/null || true && \\\n";
        $cmd .= "rm -rf \"/srv/hermes/{$serviceid}\" && \\\n";
        $cmd .= "if [ -f \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\" ]; then\n";
        $cmd .= "  rm -f \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\"\n";
        $cmd .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
        $cmd .= "fi";
        
        $result = $ssh->exec($cmd);
        logModuleCall('hermesagent', 'TerminateAccount', $cmd, $result);
        
        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->delete();
            
        return "success";
    } catch (\Exception $e) {
        return "Termination failed: " . $e->getMessage();
    }
}

/**
 * Change Service Password (rotates Dashboard Authentication Password)
 */
function hermesagent_ChangePassword($params) {
    hermesagent_setup_database();
    $serviceid = intval($params['serviceid']);
    $newPassword = $params['password'] ?: hermesagent_generate_random_password(16);
    
    $record = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceid)->first();
    if (!$record) {
        return "Hermes deployment record not found in WHMCS database.";
    }
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        $dataDir = "/srv/hermes/{$serviceid}/data";
        
        // Read existing .env, update basic auth password line, write it back
        $cmd = "if [ -f \"{$dataDir}/.env\" ]; then\n";
        // sed inline replace password
        $cmd .= "  sed -i 's/^HERMES_DASHBOARD_BASIC_AUTH_PASSWORD=.*/HERMES_DASHBOARD_BASIC_AUTH_PASSWORD=" . escapeshellarg($newPassword) . "/' \"{$dataDir}/.env\"\n";
        $cmd .= "  docker restart \"hermes-{$serviceid}\"\n";
        $cmd .= "  echo \"SUCCESS\"\n";
        $cmd .= "else\n";
        $cmd .= "  echo \"ENV_FILE_NOT_FOUND\"\n";
        $cmd .= "fi";
        
        $result = trim($ssh->exec($cmd));
        logModuleCall('hermesagent', 'ChangePassword', $cmd, $result, null, [$newPassword]);
        
        if ($result !== 'SUCCESS') {
            return "Failed to update env file on host: " . $result;
        }
        
        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->update([
                'dashboard_password' => $newPassword,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
        return "success";
    } catch (\Exception $e) {
        return "Password change failed: " . $e->getMessage();
    }
}

/**
 * Change Package Resources (CPU/Memory update)
 */
function hermesagent_ChangePackage($params) {
    $serviceid = intval($params['serviceid']);
    $resourceTier = hermesagent_resolve_param($params, 'configoption9', 'Resource Tier', 'Standard (2 vCPU / 2GB)');
    
    $cpus = '2.0';
    $memory = '2g';
    if (stripos($resourceTier, 'Starter') !== false) {
        $cpus = '1.0';
        $memory = '1g';
    } elseif (stripos($resourceTier, 'Pro') !== false) {
        $cpus = '4.0';
        $memory = '4g';
    }
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        // Update resource limits on the container
        $cmd = "docker update --cpus=\"{$cpus}\" --memory=\"{$memory}\" \"hermes-{$serviceid}\"";
        $result = $ssh->exec($cmd);
        logModuleCall('hermesagent', 'ChangePackage', $cmd, $result);
        
        return "success";
    } catch (\Exception $e) {
        return "Package change failed: " . $e->getMessage();
    }
}

/**
 * Define action buttons visible in Client Area.
 */
function hermesagent_ClientAreaCustomButtonArray() {
    return [
        'Get Stats' => 'getstats',
        'Manage LLM Providers' => 'manage_llm',
        'Restart Agent' => 'restart',
        'View Logs' => 'viewlogs',
        'Regenerate Password' => 'regenpassword',
        'Download Agent Brain' => 'downloadbackup',
        'Kill Switch' => 'killswitch',
    ];
}

/**
 * Define action buttons visible in Admin Area.
 */
function hermesagent_AdminCustomButtonArray() {
    return [
        'Restart Agent' => 'restart',
        'View Logs' => 'viewlogs',
        'Regenerate Password' => 'regenpassword',
        'Force Redeploy' => 'redeploy',
        'SSH Health Check' => 'healthcheck',
    ];
}

/**
 * Custom action: Restart Container
 */
function hermesagent_restart($params) {
    $serviceid = intval($params['serviceid']);
    try {
        $ssh = hermesagent_get_ssh_client($params);
        $cmd = "docker restart \"hermes-{$serviceid}\"";
        $result = $ssh->exec($cmd);
        logModuleCall('hermesagent', 'restart', $cmd, $result);
        return "success";
    } catch (\Exception $e) {
        return "Restart failed: " . $e->getMessage();
    }
}

/**
 * Custom action: View Logs (returns raw HTML format)
 */
function hermesagent_viewlogs($params) {
    $serviceid = intval($params['serviceid']);
    try {
        $ssh = hermesagent_get_ssh_client($params);
        $cmd = "docker logs --tail 100 \"hermes-{$serviceid}\" 2>&1";
        $result = $ssh->exec($cmd);
        logModuleCall('hermesagent', 'viewlogs', $cmd, '[truncated logs]');
        
        if (empty($result)) {
            $result = "No logs returned from container.";
        }
        
        return "<pre style='text-align: left; max-height: 400px; overflow-y: auto; color: #a9b7c6; background: #2b2b2b; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.4; border: 1px solid #3c3f41;'>" . htmlspecialchars($result) . "</pre>";
    } catch (\Exception $e) {
        return "Failed to fetch logs: " . $e->getMessage();
    }
}

/**
 * Custom action: Regenerate Dashboard Password
 */
function hermesagent_regenpassword($params) {
    $newPass = hermesagent_generate_random_password(16);
    $params['password'] = $newPass;
    $res = hermesagent_ChangePassword($params);
    if ($res === 'success') {
        return "Dashboard Password regenerated successfully! New Password: " . $newPass;
    }
    return "Failed to regenerate password: " . $res;
}

/**
 * Custom action: Get Stats (AJAX endpoint)
 */
function hermesagent_getstats($params) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $serviceid = intval($params['serviceid']);
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        // Fetch CPU and Mem Usage
        $statsCmd = "docker stats \"hermes-{$serviceid}\" --no-stream --format \"{{.CPUPerc}}|{{.MemUsage}}\" 2>/dev/null";
        $statsOutput = trim($ssh->exec($statsCmd));
        
        $cpu = '0%';
        $mem = '0B / 0B';
        if (!empty($statsOutput)) {
            $parts = explode('|', $statsOutput);
            if (count($parts) >= 2) {
                $cpu = trim($parts[0]);
                $mem = trim($parts[1]);
            }
        }
        
        // Fetch Token Usage by grepping logs
        // This is a naive implementation looking for "prompt_tokens" and "completion_tokens" in the last 1000 lines
        $logCmd = "docker logs --tail 2000 \"hermes-{$serviceid}\" 2>&1 | grep -E '\"prompt_tokens\"|\"completion_tokens\"' || true";
        $logOutput = $ssh->exec($logCmd);
        
        $promptTokens = 0;
        $completionTokens = 0;
        
        if (!empty($logOutput)) {
            preg_match_all('/"prompt_tokens"\s*:\s*(\d+)/i', $logOutput, $promptMatches);
            if (!empty($promptMatches[1])) {
                $promptTokens = array_sum($promptMatches[1]);
            }
            
            preg_match_all('/"completion_tokens"\s*:\s*(\d+)/i', $logOutput, $compMatches);
            if (!empty($compMatches[1])) {
                $completionTokens = array_sum($compMatches[1]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'cpu' => $cpu,
            'memory' => $mem,
            'tokens' => [
                'prompt' => $promptTokens,
                'completion' => $completionTokens,
                'total' => $promptTokens + $completionTokens
            ]
        ]);
        exit;
        
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/**
 * Custom action: Download Agent Brain Backup
 */
function hermesagent_downloadbackup($params) {
    $serviceid = intval($params['serviceid']);
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        $tmpFile = "/tmp/hermes_brain_{$serviceid}_" . time() . ".tar.gz";
        $dataDir = "/srv/hermes/{$serviceid}/data";
        
        // Check if data directory exists
        $check = trim($ssh->exec("if [ -d \"{$dataDir}\" ]; then echo 'EXISTS'; else echo 'NOT_FOUND'; fi"));
        if ($check !== 'EXISTS') {
            return "Agent data directory not found on host.";
        }
        
        // Compress the directory and base64 encode it so it can safely cross the SSH output stream
        $cmd = "tar -czf \"{$tmpFile}\" -C \"/srv/hermes/{$serviceid}\" data && base64 \"{$tmpFile}\" && rm -f \"{$tmpFile}\"";
        $b64 = $ssh->exec($cmd);
        
        $binary = base64_decode(trim($b64));
        if (empty($binary)) {
            return "Failed to generate or download the backup archive.";
        }
        
        // Force file download in browser
        ob_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="hermes_brain_'.$serviceid.'_'.date('Ymd').'.tar.gz"');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    } catch (\Exception $e) {
        return "Backup failed: " . $e->getMessage();
    }
}

/**
 * Custom action: Kill Switch (Wipe Agent and Data forever)
 */
function hermesagent_killswitch($params) {
    $serviceid = intval($params['serviceid']);
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        // Hard wipe container and data directory
        $cmd = "docker rm -fv \"hermes-{$serviceid}\" 2>/dev/null || true && \\\n";
        $cmd .= "rm -rf \"/srv/hermes/{$serviceid}\" && \\\n";
        $cmd .= "if [ -f \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\" ]; then\n";
        $cmd .= "  rm -f \"/etc/caddy/conf.d/hermes-{$serviceid}.conf\"\n";
        $cmd .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
        $cmd .= "fi";
        
        $result = $ssh->exec($cmd);
        logModuleCall('hermesagent', 'KillSwitch', $cmd, $result);
        
        Capsule::table('mod_hermesagent_instances')
            ->where('serviceid', $serviceid)
            ->update(['status' => 'Terminated', 'updated_at' => date('Y-m-d H:i:s')]);
            
        return "success";
    } catch (\Exception $e) {
        return "Kill Switch failed: " . $e->getMessage();
    }
}

/**
 * Custom action: Force Redeploy (Admin-only)
 */
function hermesagent_redeploy($params) {
    return hermesagent_CreateAccount($params);
}

/**
 * Custom action: SSH Health Check
 */
function hermesagent_healthcheck($params) {
    $serviceid = intval($params['serviceid']);
    
    $record = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceid)->first();
    if (!$record) {
        return "No instance mapping found in WHMCS database.";
    }
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        
        $cmd = "docker inspect --format='{{.State.Status}}' \"hermes-{$serviceid}\" 2>/dev/null || echo \"NOT_FOUND\"";
        $containerStatus = trim($ssh->exec($cmd));
        
        $cmdCheck = "STATUS=\$(curl -s -o /dev/null -w \"%{http_code}\" \"http://127.0.0.1:{$record->dash_port}/\" || echo \"000\")\n";
        $cmdCheck .= "echo \$STATUS";
        $httpStatus = trim($ssh->exec($cmdCheck));
        
        logModuleCall('hermesagent', 'healthcheck', $cmd . "\n" . $cmdCheck, "Docker: {$containerStatus}, HTTP: {$httpStatus}");
        
        $message = "Hermes Host Connection: SUCCESS\n";
        $message .= "Container Status: " . strtoupper($containerStatus) . "\n";
        $message .= "Dashboard Port Status (HTTP Code): " . $httpStatus . " (" . ($httpStatus == '401' || $httpStatus == '200' ? 'Operational' : 'Failed') . ")";
        
        return nl2br(htmlspecialchars($message));
    } catch (\Exception $e) {
        return "SSH Connection failed: " . $e->getMessage();
    }
}

/**
 * Custom action: Manage LLM Providers
 */
function hermesagent_manage_llm($params) {
    $serviceid = intval($params['serviceid']);
    
    // Default values if we can't parse them
    $vars = [
        'active_model' => '',
        'openrouter_key' => '',
        'openai_key' => '',
        'anthropic_key' => '',
        'nous_key' => '',
        'custom_url' => '',
        'custom_key' => '',
        'telegram_token' => '',
        'discord_token' => '',
        'serviceid' => $serviceid,
        'success' => isset($_GET['success']) ? true : false,
        'error' => '',
        'deployment_status' => $params['status']
    ];
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        $dataDir = "/srv/hermes/{$serviceid}/data";
        
        // Read .env
        $envData = $ssh->exec("cat \"{$dataDir}/.env\" 2>/dev/null || echo ''");
        if ($envData) {
            $lines = explode("\n", $envData);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val);
                    if ($key === 'OPENROUTER_API_KEY') $vars['openrouter_key'] = $val;
                    if ($key === 'OPENAI_API_KEY') $vars['openai_key'] = $val; // Might be custom key too, we'll disambiguate later
                    if ($key === 'ANTHROPIC_API_KEY') $vars['anthropic_key'] = $val;
                    if ($key === 'NOUS_PORTAL_API_KEY') $vars['nous_key'] = $val;
                    if ($key === 'OPENAI_API_BASE') $vars['custom_url'] = $val;
                    if ($key === 'TELEGRAM_BOT_TOKEN') $vars['telegram_token'] = $val;
                    if ($key === 'DISCORD_BOT_TOKEN') $vars['discord_token'] = $val;
                }
            }
        }
        
        // Disambiguate OpenAI key vs Custom endpoint key
        if (!empty($vars['custom_url']) && !empty($vars['openai_key'])) {
            $vars['custom_key'] = $vars['openai_key'];
            $vars['openai_key'] = ''; // It was used for custom
        }
        
        // Read config.yaml
        $yamlData = $ssh->exec("cat \"{$dataDir}/config.yaml\" 2>/dev/null || echo ''");
        if ($yamlData) {
            preg_match('/^model:\s*"?([^"\n\r]+)"?/m', $yamlData, $matches);
            if (!empty($matches[1])) {
                $vars['active_model'] = $matches[1];
            }
        }
    } catch (\Exception $e) {
        $vars['error'] = "Could not fetch live configuration: " . $e->getMessage();
    }
    
    return [
        'templatefile' => 'templates/manage_llm',
        'vars' => $vars
    ];
}

/**
 * Custom action: Update LLM Providers (POST handler)
 */
function hermesagent_update_llm($params) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: clientarea.php?action=productdetails&id=" . intval($params['serviceid']) . "&modop=custom&a=manage_llm");
        exit;
    }
    
    $serviceid = intval($params['serviceid']);
    $model = trim($_POST['active_model'] ?? '');
    
    // Build new keys array
    $keysToUpdate = [];
    if (!empty($_POST['openrouter_key'])) $keysToUpdate['OPENROUTER_API_KEY'] = trim($_POST['openrouter_key']);
    if (!empty($_POST['openai_key'])) $keysToUpdate['OPENAI_API_KEY'] = trim($_POST['openai_key']);
    if (!empty($_POST['anthropic_key'])) $keysToUpdate['ANTHROPIC_API_KEY'] = trim($_POST['anthropic_key']);
    if (!empty($_POST['nous_key'])) $keysToUpdate['NOUS_PORTAL_API_KEY'] = trim($_POST['nous_key']);
    if (!empty($_POST['telegram_token'])) $keysToUpdate['TELEGRAM_BOT_TOKEN'] = trim($_POST['telegram_token']);
    if (!empty($_POST['discord_token'])) $keysToUpdate['DISCORD_BOT_TOKEN'] = trim($_POST['discord_token']);
    
    if (!empty($_POST['custom_url'])) {
        $keysToUpdate['OPENAI_API_BASE'] = trim($_POST['custom_url']);
        if (!empty($_POST['custom_key'])) {
            $keysToUpdate['OPENAI_API_KEY'] = trim($_POST['custom_key']); // Overrides standard openai if both submitted
        }
    }
    
    try {
        $ssh = hermesagent_get_ssh_client($params);
        $dataDir = "/srv/hermes/{$serviceid}/data";
        
        $cmd = "";
        
        // 1. Update config.yaml for the model
        if (!empty($model)) {
            // Escape slashes for sed
            $escModel = str_replace('/', '\/', $model);
            $cmd .= "sed -i 's/^model:.*/model: \"{$escModel}\"/' \"{$dataDir}/config.yaml\"\n";
        }
        
        // 2. Update .env for each key
        // We will remove existing occurrences of these keys, then append them
        $allPossibleKeys = ['OPENROUTER_API_KEY', 'OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'NOUS_PORTAL_API_KEY', 'OPENAI_API_BASE', 'TELEGRAM_BOT_TOKEN', 'DISCORD_BOT_TOKEN'];
        foreach ($allPossibleKeys as $k) {
            $cmd .= "sed -i '/^{$k}=/d' \"{$dataDir}/.env\"\n";
        }
        foreach ($keysToUpdate as $k => $v) {
            // Escape value safely
            $safeV = escapeshellarg($v);
            $cmd .= "echo \"{$k}={$safeV}\" >> \"{$dataDir}/.env\"\n";
        }
        
        // 3. Restart container to apply
        $cmd .= "docker restart \"hermes-{$serviceid}\"\n";
        
        $ssh->exec($cmd);
        logModuleCall('hermesagent', 'update_llm', $cmd, 'Success');
        
        header("Location: clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=manage_llm&success=1");
        exit;
        
    } catch (\Exception $e) {
        logModuleCall('hermesagent', 'update_llm', 'Failed', $e->getMessage());
        // Simple error redirect
        header("Location: clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=manage_llm&error=1");
        exit;
    }
}

/**
 * Client Area Output page
 */
function hermesagent_ClientArea($params) {
    hermesagent_setup_database();
    $serviceid = intval($params['serviceid']);
    
    $record = Capsule::table('mod_hermesagent_instances')->where('serviceid', $serviceid)->first();
    if (!$record) {
        return [
            'templatefile' => 'templates/clientarea',
            'vars' => [
                'deployment_status' => 'Pending Provisioning',
                'dashboard_url' => '',
                'api_url' => '',
                'api_enabled' => false,
                'username' => '',
                'password' => '',
                'api_key' => '',
                'error' => 'No active server deployment found.'
            ]
        ];
    }
    
    // Determine the IP/host address to use for connections
    $isSecure = !empty($params['serversecure']) && ($params['serversecure'] === true || $params['serversecure'] === 'on' || $params['serversecure'] === '1');
    $host = $params['serverip'];
    $serverHostname = $params['serverhostname'];
    
    $dashboardUrl = '';
    $apiUrl = '';
    
    if ($isSecure) {
        $dashboardUrl = "https://hermes-{$serviceid}.hermes.deltadns.xyz";
        $apiUrl = "https://hermes-api-{$serviceid}.hermes.deltadns.xyz/v1";
    } else {
        $dashboardUrl = "http://{$host}:{$record->dash_port}";
        $apiUrl = "http://{$host}:{$record->api_port}/v1";
    }
    
    $enableApiServer = hermesagent_resolve_param($params, 'configoption8', 'Enable OpenAI-Compatible API', 'no');
    $apiEnabledVal = (strtolower($enableApiServer) === 'yes' || $enableApiServer === '1' || $enableApiServer === true || $enableApiServer === 'on');
    
    return [
        'templatefile' => 'templates/clientarea',
        'vars' => [
            'deployment_status' => $record->status,
            'dashboard_url' => $dashboardUrl,
            'api_url' => $apiUrl,
            'api_enabled' => $apiEnabledVal,
            'username' => $record->dashboard_username,
            'password' => $record->dashboard_password,
            'api_key' => $record->api_key,
            'dash_port' => $record->dash_port,
            'api_port' => $record->api_port,
            'is_secure' => $isSecure
        ]
    ];
}
