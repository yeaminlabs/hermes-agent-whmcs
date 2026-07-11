<?php
/**
 * Hermes Agent Manager Addon Module
 *
 * @copyright Copyright (c) snbdhost 2026
 * @license MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Configure the addon module.
 */
function hermesagent_config() {
    return [
        'name' => 'Hermes Agent Manager',
        'description' => 'Admin tools to automate product configurations (Custom Fields & Configurable Options) and manage active dockerized client deployments.',
        'author' => 'snbdhost',
        'language' => 'english',
        'version' => '1.0',
        'fields' => []
    ];
}

/**
 * Activate the addon module - setup database structure.
 */
function hermesagent_activate() {
    try {
        // Ensure our instances mapping table exists
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
        return [
            'status' => 'success',
            'description' => 'Hermes Agent Manager activated and database tables configured successfully.'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Deactivate the addon module.
 */
function hermesagent_deactivate() {
    return [
        'status' => 'success',
        'description' => 'Hermes Agent Manager deactivated.'
    ];
}

/**
 * Helper to check and insert custom field for product.
 */
function hermesagent_addon_create_custom_field($productId, $name, $type, $desc, $showOrder = true, $required = true) {
    $field = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('relid', $productId)
        ->where('fieldname', $name)
        ->first();
        
    if (!$field) {
        Capsule::table('tblcustomfields')->insert([
            'type' => 'product',
            'relid' => $productId,
            'fieldname' => $name,
            'fieldtype' => $type,
            'description' => $desc,
            'fieldoptions' => '',
            'regexpr' => '',
            'adminonly' => '',
            'required' => $required ? 'on' : '',
            'showorder' => $showOrder ? 'on' : '',
            'showinvoice' => '',
            'sortorder' => 0
        ]);
    } else {
        Capsule::table('tblcustomfields')
            ->where('id', $field->id)
            ->update([
                'required' => $required ? 'on' : ''
            ]);
    }
}

/**
 * Helper to setup Configurable Option Group, Options, Subs, and default pricing.
 */
function hermesagent_addon_setup_config_options($productId) {
    $groupName = "Hermes Agent Options (Product #" . $productId . ")";
    
    // 1. Create or fetch group
    $groupId = Capsule::table('tblproductconfiggroups')
        ->where('name', $groupName)
        ->value('id');
        
    if (!$groupId) {
        $groupId = Capsule::table('tblproductconfiggroups')->insertGetId([
            'name' => $groupName,
            'description' => 'Auto-generated configurable options for Hermes Agent provisioning',
        ]);
    }
    
    // Link group to product
    $linkExists = Capsule::table('tblproductconfiglinks')
        ->where('gid', $groupId)
        ->where('pid', $productId)
        ->exists();
    if (!$linkExists) {
        Capsule::table('tblproductconfiglinks')->insert([
            'gid' => $groupId,
            'pid' => $productId
        ]);
    }
    
    // Fetch OpenRouter Models dynamically
    $openRouterModels = [];
    try {
        $ch = curl_init('https://openrouter.ai/api/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $model) {
                    $id = $model['id'];
                    $name = $model['name'];
                    
                    // Check if free
                    $isFree = false;
                    if (isset($model['pricing']) && isset($model['pricing']['prompt']) && isset($model['pricing']['completion'])) {
                        if (floatval($model['pricing']['prompt']) == 0 && floatval($model['pricing']['completion']) == 0) {
                            $isFree = true;
                        }
                    }
                    
                    if ($isFree) {
                        $name .= ' (Free)';
                    }
                    
                    $openRouterModels[] = $id . '|' . $name;
                }
            }
        }
    } catch (\Exception $e) {
        // Ignore errors and fallback
    }
    
    $modelSubs = [
        'hermes-4-405b|Hermes 4 405B (Default)',
        'gpt-4o|GPT-4o',
        'claude-3-5-sonnet|Claude 3.5 Sonnet'
    ];
    
    if (!empty($openRouterModels)) {
        $modelSubs = array_merge($modelSubs, $openRouterModels);
    }

    // 2. Define Options
    $options = [
        [
            'name' => 'LLM Provider',
            'type' => 1, // Dropdown
            'subs' => [
                'nous_portal|Nous Portal (recommended)',
                'openrouter|OpenRouter',
                'openai|OpenAI',
                'anthropic|Anthropic',
                'custom|Custom Endpoint'
            ]
        ],
        [
            'name' => 'Resource Tier',
            'type' => 1, // Dropdown
            'subs' => [
                'Starter (1 vCPU / 1GB)|Starter (1 vCPU / 1GB)',
                'Standard (2 vCPU / 2GB)|Standard (2 vCPU / 2GB)',
                'Pro (4 vCPU / 4GB)|Pro (4 vCPU / 4GB)'
            ]
        ],
        [
            'name' => 'Enable OpenAI-Compatible API',
            'type' => 1, // Dropdown
            'subs' => [
                'no|No',
                'yes|Yes'
            ]
        ],
        [
            'name' => 'Model',
            'type' => 1, // Dropdown
            'subs' => $modelSubs
        ],
        [
            'name' => 'Messaging Platform',
            'type' => 1, // Dropdown
            'subs' => [
                'None|None',
                'Telegram|Telegram',
                'Discord|Discord',
                'Slack|Slack'
            ]
        ]
    ];
    
    $currencies = Capsule::table('tblcurrencies')->pluck('id');
    
    foreach ($options as $opt) {
        $optionId = Capsule::table('tblproductconfigoptions')
            ->where('gid', $groupId)
            ->where('optionname', 'like', $opt['name'] . '%')
            ->value('id');
            
        if (!$optionId) {
            $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
                'gid' => $groupId,
                'optionname' => $opt['name'],
                'optiontype' => $opt['type'],
                'qtyminimum' => 0,
                'qtymaximum' => 0,
                'order' => 0
            ]);
        }
        
        // Add Sub options (dropdown items) and pricing
        foreach ($opt['subs'] as $subName) {
            $subId = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $optionId)
                ->where('optionname', 'like', $subName . '%')
                ->value('id');
                
            if (!$subId) {
                $subId = Capsule::table('tblproductconfigoptionssub')->insertGetId([
                    'configid' => $optionId,
                    'optionname' => $subName,
                    'sortorder' => 0,
                    'hidden' => 0
                ]);
            }
            
            // Create $0.00 pricing records for each currency
            foreach ($currencies as $currId) {
                $priceExists = Capsule::table('tblpricing')
                    ->where('type', 'configoptions')
                    ->where('currency', $currId)
                    ->where('relid', $subId)
                    ->exists();
                if (!$priceExists) {
                    Capsule::table('tblpricing')->insert([
                        'type' => 'configoptions',
                        'currency' => $currId,
                        'relid' => $subId,
                        'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                        'monthly' => 0, 'quarterly' => 0, 'semiannually' => 0, 'annually' => 0, 'biennially' => 0, 'triennially' => 0
                    ]);
                }
            }
        }
    }
}

/**
 * Output admin UI for the addon module.
 */
function hermesagent_output($vars) {
    // Automatically fix 'required' flag for optional fields across all products
    try {
        Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->whereIn('fieldname', ['Bot Token', 'Custom Endpoint URL'])
            ->update(['required' => '']);
    } catch (\Exception $e) {
        // ignore
    }

    // 1. Handle One-Click config submit
    $message = '';
    if (isset($_POST['configure_product'])) {
        $pid = intval($_POST['product_id']);
        if ($pid > 0) {
            try {
                // Add Custom fields
                hermesagent_addon_create_custom_field($pid, 'Provider API Key', 'password', 'API key/token for the selected inference provider (client-supplied, stored encrypted).', true, true);
                hermesagent_addon_create_custom_field($pid, 'Dashboard Username', 'text', 'Login username for the Hermes web dashboard / Desktop Remote Gateway.', true, true);
                hermesagent_addon_create_custom_field($pid, 'Bot Token', 'password', 'Bot token for the selected messaging platform (leave blank if None).', true, false);
                hermesagent_addon_create_custom_field($pid, 'Custom Endpoint URL', 'text', 'Only used when Custom OpenAI-compatible endpoint is selected.', true, false);
                
                // Add Config options
                hermesagent_addon_setup_config_options($pid);
                
                $message = '<div class="alert alert-success" style="padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-check-circle"></i> Success! Product #' . $pid . ' has been fully configured with Hermes Agent options and custom fields. Customers will now see these options during checkout.</div>';
            } catch (\Exception $e) {
                $message = '<div class="alert alert-danger" style="padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Failed: ' . $e->getMessage() . '</div>';
            }
        }
    }

    // 2. Fetch list of products configured with our module
    $products = Capsule::table('tblproducts')
        ->where('servertype', 'hermesagent')
        ->select('id', 'name', 'type')
        ->get();

    // 3. Fetch deployed instances
    $deployments = Capsule::table('mod_hermesagent_instances')
        ->join('tblhosting', 'mod_hermesagent_instances.serviceid', '=', 'tblhosting.id')
        ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblservers', 'tblhosting.server', '=', 'tblservers.id')
        ->select(
            'mod_hermesagent_instances.*',
            'tblhosting.domain',
            'tblhosting.id as hosting_id',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblclients.companyname',
            'tblproducts.name as product_name',
            'tblservers.ipaddress as server_ip',
            'tblservers.hostname as server_hostname',
            'tblservers.secure as server_secure'
        )
        ->get();

    // 4. Calculate Stats
    $total = count($deployments);
    $active = 0;
    $suspended = 0;
    $error = 0;
    foreach ($deployments as $d) {
        if ($d->status === 'Active') $active++;
        elseif ($d->status === 'Suspended') $suspended++;
        else $error++;
    }

    // Render interface with beautiful custom dark-mode styled cards
    echo $message;
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .ha-wrapper {
            font-family: 'Outfit', sans-serif;
            color: #333;
            margin-top: 15px;
        }
        .ha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ddd;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .ha-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #2b3e50;
        }
        .ha-subtitle {
            font-size: 13px;
            color: #666;
            margin: 3px 0 0 0;
        }
        .ha-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .ha-stat-card {
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ha-stat-info h5 {
            margin: 0 0 5px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #858796;
        }
        .ha-stat-info h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #2e3d49;
        }
        .ha-stat-icon {
            font-size: 32px;
            color: #dddfeb;
        }
        .stat-blue { border-left: 4px solid #4e73df; }
        .stat-green { border-left: 4px solid #1cc88a; }
        .stat-yellow { border-left: 4px solid #f6c23e; }
        .stat-red { border-left: 4px solid #e74a3b; }
        
        .ha-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }
        @media (max-width: 992px) {
            .ha-grid { grid-template-columns: 1fr; }
        }
        .ha-panel {
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        .ha-panel-header {
            padding: 15px 20px;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ha-panel-title {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #4e73df;
        }
        .ha-panel-body {
            padding: 20px;
        }
        .btn-ha-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
            color: #fff;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-ha-primary:hover {
            opacity: 0.9;
            color: #fff;
        }
        .table-ha {
            width: 100%;
            border-collapse: collapse;
        }
        .table-ha th {
            background: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            color: #4e73df;
        }
        .table-ha td {
            border-bottom: 1px solid #e3e6f0;
            padding: 12px;
            font-size: 13.5px;
        }
        .ha-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-suspended { background: #fff3cd; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #e2e3e5; color: #383d41; }
    </style>

    <div class="ha-wrapper">
        <div class="ha-header">
            <div>
                <h2 class="ha-title">Hermes Agent Manager Dashboard</h2>
                <p class="ha-subtitle">Automate product configuration and monitor deployments</p>
            </div>
            <div>
                <span class="ha-badge badge-active" style="padding: 6px 12px; font-size:12px;">Hermes Module Active</span>
            </div>
        </div>

        <!-- Stat Widgets -->
        <div class="ha-stats-row">
            <div class="ha-stat-card stat-blue">
                <div class="ha-stat-info">
                    <h5>Total Agents</h5>
                    <h2><?php echo $total; ?></h2>
                </div>
                <div class="ha-stat-icon"><i class="fas fa-server"></i></div>
            </div>
            <div class="ha-stat-card stat-green">
                <div class="ha-stat-info">
                    <h5>Healthy / Active</h5>
                    <h2><?php echo $active; ?></h2>
                </div>
                <div class="ha-stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="ha-stat-card stat-yellow">
                <div class="ha-stat-info">
                    <h5>Suspended</h5>
                    <h2><?php echo $suspended; ?></h2>
                </div>
                <div class="ha-stat-icon"><i class="fas fa-pause-circle"></i></div>
            </div>
            <div class="ha-stat-card stat-red">
                <div class="ha-stat-info">
                    <h5>Failed / Error</h5>
                    <h2><?php echo $error; ?></h2>
                </div>
                <div class="ha-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>

        <div class="ha-grid">
            <!-- Left Side: Product Configuration Auto-Setup -->
            <div class="ha-panel">
                <div class="ha-panel-header">
                    <h4 class="ha-panel-title">One-Click Product Setup</h4>
                </div>
                <div class="ha-panel-body">
                    <p style="font-size: 13.5px; color:#555; line-height: 1.5; margin-bottom:15px;">
                        Select a Hermes hosting product. This tool automatically configures the necessary **Custom Fields** and **Configurable Options** so customers can input their API keys, messaging platforms, and resource limits during checkout.
                    </p>
                    
                    <?php if (count($products) > 0): ?>
                        <form method="post" action="">
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Select Product:</label>
                                <select name="product_id" class="form-control" style="height:40px; border-radius:6px;">
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p->id; ?>"><?php echo htmlspecialchars($p->name); ?> (ID: <?php echo $p->id; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="configure_product" class="btn-ha-primary" style="width:100%;">
                                <i class="fas fa-cog"></i> Run Setup Configuration
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning" style="font-size:13px; padding:10px; border-radius:6px; margin:0;">
                            <i class="fas fa-info-circle"></i> No products found using the <strong>hermesagent</strong> server module. Create a product first!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Active Deployments List -->
            <div class="ha-panel">
                <div class="ha-panel-header">
                    <h4 class="ha-panel-title">Active client Deployments</h4>
                </div>
                <div class="ha-panel-body" style="padding:0; overflow-x:auto;">
                    <?php if (count($deployments) > 0): ?>
                        <table class="table-ha">
                            <thead>
                                <tr>
                                    <th>Service ID</th>
                                    <th>Client</th>
                                    <th>Product</th>
                                    <th>VPS Server IP</th>
                                    <th>Dashboard URL</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deployments as $d): ?>
                                    <?php
                                    $clientName = htmlspecialchars($d->firstname . ' ' . $d->lastname);
                                    if ($d->companyname) {
                                        $clientName .= ' (' . htmlspecialchars($d->companyname) . ')';
                                    }
                                    
                                    // Resolve dashboard URL
                                    $dashUrl = '';
                                    if ($d->server_secure && !empty($d->server_hostname)) {
                                        $dashUrl = "https://hermes-{$d->serviceid}.{$d->server_hostname}";
                                    } else {
                                        $dashUrl = "http://{$d->server_ip}:{$d->dash_port}";
                                    }
                                    
                                    $statusClass = 'badge-pending';
                                    if ($d->status === 'Active') $statusClass = 'badge-active';
                                    elseif ($d->status === 'Suspended') $statusClass = 'badge-suspended';
                                    elseif ($d->status === 'Error') $statusClass = 'badge-error';
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo $d->serviceid; ?></strong></td>
                                        <td><?php echo $clientName; ?></td>
                                        <td><?php echo htmlspecialchars($d->product_name); ?></td>
                                        <td><code><?php echo htmlspecialchars($d->server_ip); ?></code></td>
                                        <td>
                                            <a href="<?php echo $dashUrl; ?>" target="_blank" style="text-decoration:none; font-weight:600;">
                                                Port: <?php echo $d->dash_port; ?> <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                            </a>
                                        </td>
                                        <td><span class="ha-badge <?php echo $statusClass; ?>"><?php echo $d->status; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 20px; color:#777;">
                            <i class="fas fa-server" style="font-size:40px; margin-bottom:10px; color:#ccc;"></i>
                            <p style="margin:0; font-size:14px;">No active Hermes Agent deployments found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
