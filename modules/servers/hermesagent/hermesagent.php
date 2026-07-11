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
  -v \"{$dataDir}:/opt/data\" \\
  -p \"{$bindIp}:{$dashPort}:9119\" \\
  -p \"{$bindIp}:{$apiPort}:8642\" \\
  nousresearch/hermes-agent:{$dockerImageTag} gateway run\n";
        
        // Add Reverse Proxy config if Caddy is present and secure is active
        if ($isSecure && !empty($params['serverhostname'])) {
            $hostname = $params['serverhostname'];
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
            $setupCmds .= "  systemctl reload caddy || caddy reload --config /etc/caddy/Caddyfile || true\n";
            $setupCmds .= "fi\n";
        }
        
        // Run health check (Wait up to 40 seconds for the application to boot)
        $setupCmds .= "for i in {1..20}; do\n";
        $setupCmds .= "  STATUS=\$(curl -s -o /dev/null -w \"%{http_code}\" \"http://127.0.0.1:{$dashPort}/\" || echo \"000\")\n";
        $setupCmds .= "  if [ \"\$STATUS\" = \"200\" ] || [ \"\$STATUS\" = \"401\" ]; then\n";
        $setupCmds .= "    echo \"HEALTHY\"\n";
        $setupCmds .= "    break\n";
        $setupCmds .= "  fi\n";
        $setupCmds .= "  sleep 2\n";
        $setupCmds .= "done\n";
        $setupCmds .= "if [ \"\$STATUS\" != \"200\" ] && [ \"\$STATUS\" != \"401\" ]; then\n";
        $setupCmds .= "  if [ \"\$(docker inspect -f '{{.State.Running}}' hermes-{$serviceid} 2>/dev/null)\" = \"true\" ]; then\n";
        $setupCmds .= "    echo \"HEALTHY\" # App is slow to boot but container is running\n";
        $setupCmds .= "  else\n";
        $setupCmds .= "    echo \"NOT_READY_STATUS_\$STATUS\"\n";
        $setupCmds .= "  fi\n";
        $setupCmds .= "fi\n";

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
        $cmd = "docker stop \"hermes-{$serviceid}\"";
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
        $cmd = "docker start \"hermes-{$serviceid}\"";
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
        'Restart Agent' => 'restart',
        'View Logs' => 'viewlogs',
        'Regenerate Password' => 'regenpassword',
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
    
    if ($isSecure && !empty($serverHostname)) {
        $dashboardUrl = "https://hermes-{$serviceid}.{$serverHostname}";
        $apiUrl = "https://hermes-api-{$serviceid}.{$serverHostname}/v1";
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
