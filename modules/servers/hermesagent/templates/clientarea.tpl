<!-- Google Fonts Integration -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Aggressively hide default WHMCS theme elements that clutter the page */
    div[id="cPanelConnect"], 
    .cpanel-feature,
    .panel-cpanel {
        display: none !important;
    }
    
    .hermes-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #ffffff;
        border-radius: 12px;
        padding: 35px;
        color: #111827;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin: 20px 0;
        position: relative;
        border: 1px solid #e5e7eb;
    }

    .hermes-container::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 3px;
        background: #CC0000;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .hermes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
        border-bottom: 1px solid #f3f4f6;
        padding-bottom: 20px;
    }

    .hermes-logo-area {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .hermes-logo-wrapper {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hermes-logo-icon {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .hermes-title {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        color: #111827;
    }

    .hermes-subtitle {
        font-size: 13px;
        color: #6b7280;
        margin: 4px 0 0 0;
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .status-active {
        background-color: #111827;
        color: #ffffff;
    }
    
    .status-active .status-dot {
        color: #10b981;
    }

    .status-pending {
        background-color: #f3f4f6;
        color: #4b5563;
    }

    .status-suspended {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        color: #ef4444;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: currentColor;
        display: inline-block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(0.95); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.5; }
    }
    
    /* EKG Heartbeat Animation */
    .ekg-line {
        stroke-dasharray: 300;
        stroke-dashoffset: 300;
        animation: drawEkg 2.5s linear infinite;
    }
    @keyframes drawEkg {
        0% { stroke-dashoffset: 300; }
        30% { stroke-dashoffset: 0; }
        100% { stroke-dashoffset: -300; }
    }
    
    .metric-box {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        flex: 1;
    }
    
    .metric-value {
        font-family: 'Fira Code', monospace;
        font-size: 16px;
        font-weight: 700;
        color: #CC0000;
        display: block;
        margin-bottom: 2px;
    }
    
    .metric-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .hermes-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .hermes-grid {
            grid-template-columns: 1fr;
        }
    }

    .hermes-card {
        background: #fafafa;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        position: relative;
    }

    .card-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin-top: 0;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-title i {
        color: #111827;
        font-size: 16px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
    }

    .info-value-container {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .code-val {
        font-family: 'Fira Code', 'Courier New', monospace;
        background: #ffffff;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        font-size: 13px;
        color: #111827;
        display: inline-block;
        font-weight: 500;
    }
    
    .code-val.url-val {
        color: #CC0000;
    }

    .btn-icon-only {
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 6px;
        border-radius: 6px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon-only:hover {
        color: #111827;
        background: #e5e7eb;
    }

    .hermes-btn-primary {
        background: #CC0000;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .hermes-btn-primary:hover {
        background: #aa0000;
        color: white;
        text-decoration: none;
    }
    
    .hermes-btn-purple {
        background: #111827;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .hermes-btn-purple:hover {
        background: #000000;
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    /* Step-by-Step pairing box */
    .pairing-box {
        background: #fafafa;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        margin-top: 0;
    }

    .pairing-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin-top: 0;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .pairing-title i {
        font-size: 16px;
        color: #111827;
    }

    .step-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .step-item {
        display: flex;
        gap: 15px;
        margin-bottom: 16px;
        font-size: 13px;
        color: #4b5563;
        line-height: 1.6;
    }

    .step-number {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #CC0000;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        flex-shrink: 0;
    }

    .clipboard-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 9999;
        pointer-events: none;
    }
    
    .toast-show {
        opacity: 1 !important;
    }
</style>

<div class="hermes-container">
    {if $deployment_status eq 'Suspended' or $deployment_status eq 'Terminated'}
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); backdrop-filter: blur(8px); z-index: 99; display: flex; align-items: center; justify-content: center; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.3);">
        <div style="background: #fef2f2; padding: 30px; border-radius: 12px; border: 2px solid #ef4444; max-width: 85%; text-align: center; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.2);">
            <i class="fas fa-ban" style="font-size: 40px; color: #ef4444; margin-bottom: 15px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #991b1b; font-size: 20px;">Account {$deployment_status}</h3>
            <p style="color: #7f1d1d; margin: 0; font-size: 14px; line-height: 1.5;">Your Agent account has been suspended or flagged. This typically happens due to a missed payment. Please check your invoices and clear the due dates before it gets terminated. Terminated or Suspended users will not be able to recover any agent data or API keys from their agent.</p>
        </div>
    </div>
    {/if}
    <div class="hermes-header">
        <div class="hermes-logo-area">
            <div class="hermes-logo-wrapper">
                <img src="https://raw.githubusercontent.com/lobehub/lobe-icons/refs/heads/master/packages/static-png/light/hermesagent.png" class="hermes-logo-icon" alt="Hermes Logo" />
            </div>
            <div>
                <h3 class="hermes-title">Hermes Cloud Agent</h3>
                <p class="hermes-subtitle">Managed dockerized deployment by SNBD Host</p>
            </div>
        </div>
        <div class="header-actions">
            {if $deployment_status eq 'Active'}
                <span class="status-badge status-active">
                    <span class="status-dot"></span>
                    Running
                </span>
                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=manage_llm" class="hermes-btn-primary">
                    <i class="fas fa-microchip"></i> Manage LLM Providers
                </a>
            {elseif $deployment_status eq 'Suspended'}
                <span class="status-badge status-suspended">
                    Suspended
                </span>
            {else}
                <span class="status-badge status-pending">
                    <span class="status-dot"></span>
                    {$deployment_status}
                </span>
            {/if}
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171;">
            <i class="fas fa-exclamation-triangle"></i> {$error}
        </div>
    {/if}

    <div style="background: #e6ffed; border: 1px solid #76e38c; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 15px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/2/21/Nvidia_logo.svg" alt="Nvidia Logo" style="width: 80px; flex-shrink: 0; margin-top: 5px;">
        <div>
            <h4 style="margin: 0 0 10px 0; color: #1e4620; font-size: 16px;">Powered by Nvidia Nemotron</h4>
            <p style="margin: 0; color: #24592a; font-size: 14px; line-height: 1.5;">
                This agent is powered by <a href="https://research.nvidia.com/labs/nemotron/Nemotron-3/" target="_blank" style="color: #0366d6; font-weight: bold; text-decoration: underline;">Nvidia Nemotron-3</a> technology. You are exclusively allowed to use the model <strong><code>nvidia.nemotron-nano-3-30b</code></strong> for your agentic work. Tokens are already supplied up to <strong>1.2 Billion (input and output combined) for the first 15 days</strong>. This is enough to run an agency and do minimal to medium level tasks all around the day for a month! API usage depends on how hard the task is. All you have to do is switch to the <strong><code>nvidia.nemotron-nano-3-30b</code></strong> model and it will work instantly.
            </p>
        </div>
    </div>

    <div class="hermes-grid">
        <!-- Dashboard Credentials Card -->
        <div class="hermes-card">
            <h4 class="card-title">
                <i class="fas fa-sliders-h"></i> Gateway Dashboard Connection
            </h4>
            
            <div class="info-row">
                <span class="info-label">Web Dashboard URL</span>
                <div class="info-value-container">
                    {if $dashboard_url}
                        <span class="code-val url-val">{$dashboard_url}</span>
                        <button class="btn-icon-only" onclick="copyToClipboard('{$dashboard_url}', 'Dashboard URL copied!')" title="Copy URL">
                            <i class="far fa-copy"></i>
                        </button>
                    {else}
                        <span class="text-muted">Not Available</span>
                    {/if}
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Auth Username</span>
                <div class="info-value-container">
                    <span class="code-val">{$username}</span>
                    <button class="btn-icon-only" onclick="copyToClipboard('{$username}', 'Username copied!')" title="Copy Username">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Auth Password</span>
                <div class="info-value-container">
                    <span class="code-val" id="dash-pass" style="-webkit-text-security: disc;">{$password}</span>
                    <button class="btn-icon-only" onclick="togglePasswordVisibility()" title="Toggle View" id="toggle-pass-btn">
                        <i class="far fa-eye"></i>
                    </button>
                    <button class="btn-icon-only" onclick="copyToClipboard('{$password}', 'Password copied!')" title="Copy Password">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div class="info-row">
                <span class="info-label">Dashboard Port</span>
                <div class="info-value-container">
                    <span class="code-val">{$dash_port}</span>
                </div>
            </div>

            <div style="margin-top: 25px; text-align: right;">
                {if $dashboard_url}
                    <a href="{$dashboard_url}" target="_blank" class="hermes-btn-purple">
                        Open Dashboard <i class="fas fa-arrow-right"></i>
                    </a>
                {/if}
            </div>
        </div>

        <!-- Agent Neural Link -->
        <div class="hermes-card">
            <h4 class="card-title"><i class="fas fa-heartbeat" style="color: #CC0000;"></i> Agent Neural Link</h4>
            
            {if $deployment_status eq 'Active'}
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 20px; overflow: hidden; position: relative;">
                    <div style="position: absolute; top: 10px; right: 15px; display: flex; align-items: center; gap: 5px; font-size: 11px; color: #10b981; font-weight: 600;">
                        <span class="status-dot" style="color: #10b981;"></span> SYNCHRONIZED
                    </div>
                    <p style="font-size: 12px; color: #6b7280; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px;">Container Link Stream</p>
                    
                    <svg width="100%" height="50" viewBox="0 0 300 50" preserveAspectRatio="none" style="filter: drop-shadow(0 0 4px rgba(204, 0, 0, 0.3));">
                        <!-- Grid background -->
                        <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                            <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#f3f4f6" stroke-width="1"/>
                        </pattern>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                        <!-- EKG Line -->
                        <path class="ekg-line" d="M 0 25 L 100 25 L 110 10 L 120 45 L 130 15 L 140 30 L 150 25 L 300 25" fill="none" stroke="#CC0000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <div class="metric-box">
                        <span class="metric-value">{$serviceid}</span>
                        <span class="metric-label">Container ID</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-value">LIVE</span>
                        <span class="metric-label">Uptime Status</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-value">OK</span>
                        <span class="metric-label">Memory Allocation</span>
                    </div>
                </div>
                
                <p style="font-size: 12px; color: #6b7280; margin: 0; line-height: 1.5;">
                    Your Hermes Agent is fully deployed and listening for remote connections. System resources are actively monitored.
                </p>
            {else}
                <div style="padding: 40px 20px; text-align: center; color: #6b7280;">
                    <i class="fas fa-power-off" style="font-size: 32px; color: #d1d5db; margin-bottom: 15px;"></i>
                    <p style="margin: 0; font-size: 13px;">Neural link offline. Agent is not currently active.</p>
                </div>
            {/if}
        </div>
    </div>

    <!-- Analytics & Usage Dashboard -->
    {if $deployment_status eq 'Active'}
    <div class="pairing-box" style="margin-top: 20px;">
        <h4 class="pairing-title">
            <i class="fas fa-chart-line"></i> Analytics & Usage
        </h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">CPU Usage</div>
                <div id="stat-cpu" style="font-family: 'Fira Code', monospace; font-size: 18px; font-weight: 700; color: #111827;">{$stat_cpu}</div>
            </div>
            <div style="background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Memory Usage</div>
                <div id="stat-mem" style="font-family: 'Fira Code', monospace; font-size: 18px; font-weight: 700; color: #111827;">{$stat_mem}</div>
            </div>
        </div>

        <div style="position: relative; height: 250px; width: 100%;">
            <canvas id="tokenUsageChart"></canvas>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('tokenUsageChart').getContext('2d');
        const promptTokens = {$stat_prompt_tokens|default:0};
        const completionTokens = {$stat_completion_tokens|default:0};
        
        const hasData = (promptTokens > 0 || completionTokens > 0);
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: hasData ? ['Prompt Tokens', 'Completion Tokens'] : ['No Data Yet'],
                datasets: [{
                    data: hasData ? [promptTokens, completionTokens] : [1],
                    backgroundColor: hasData ? ['#3b82f6', '#10b981'] : ['#e5e7eb'],
                    hoverBackgroundColor: hasData ? ['#2563eb', '#059669'] : ['#d1d5db'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: "'Outfit', sans-serif"
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Recent Token Processing',
                        font: {
                            family: "'Outfit', sans-serif",
                            size: 14,
                            weight: '600'
                        },
                        color: '#4b5563'
                    }
                },
                cutout: '70%'
            }
        });
    });
    </script>
    {/if}

    <!-- Website Live Chat Widget (Only if API Enabled) -->
    <div class="pairing-box" style="margin-top: 20px;">
        <h4 class="pairing-title">
            <i class="fas fa-comment-dots"></i> Website Live Chat Widget
        </h4>
        <p style="font-size: 13px; color: #4b5563; margin-bottom: 15px;">
            Copy and paste this HTML snippet into the <code>&lt;body&gt;</code> of your website (WordPress, Shopify, etc.) to instantly add a floating AI customer support chat that connects directly to your Hermes Agent.
        </p>
        
        <div style="position: relative;">
            <textarea id="hermesWidgetCode" readonly style="width: 100%; height: 200px; padding: 15px; background: #1e1e1e; color: #d4d4d4; font-family: 'Fira Code', monospace; font-size: 12px; border-radius: 8px; border: 1px solid #333; resize: vertical;" onclick="this.select();">
&lt;!-- Hermes Agent Live Chat Widget --&gt;
&lt;script&gt;
(function() {literal}{{/literal}
    const API_URL = "{$api_url}/v1/chat/completions";
    const API_KEY = "{$api_key}";
{literal}
    const style = document.createElement('style');
    style.innerHTML = `
        #hermes-chat-widget { position: fixed; bottom: 20px; right: 20px; z-index: 999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        #hermes-chat-button { width: 60px; height: 60px; border-radius: 50%; background: #3b82f6; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s; border: none; outline: none; }
        #hermes-chat-button:hover { transform: scale(1.05); }
        #hermes-chat-button svg { fill: white; width: 28px; height: 28px; }
        #hermes-chat-window { display: none; width: 350px; height: 500px; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); flex-direction: column; overflow: hidden; margin-bottom: 15px; position: absolute; bottom: 70px; right: 0; }
        #hermes-chat-header { background: #3b82f6; color: white; padding: 15px 20px; font-weight: 600; font-size: 16px; display: flex; justify-content: space-between; align-items: center; }
        #hermes-chat-close { cursor: pointer; opacity: 0.8; }
        #hermes-chat-close:hover { opacity: 1; }
        #hermes-chat-messages { flex: 1; overflow-y: auto; padding: 15px; background: #f9fafb; display: flex; flex-direction: column; gap: 10px; }
        .hermes-msg { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
        .hermes-msg-user { background: #3b82f6; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .hermes-msg-bot { background: white; color: #111827; border: 1px solid #e5e7eb; align-self: flex-start; border-bottom-left-radius: 2px; }
        #hermes-chat-input-container { padding: 15px; background: white; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; }
        #hermes-chat-input { flex: 1; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; outline: none; font-size: 14px; }
        #hermes-chat-input:focus { border-color: #3b82f6; }
        #hermes-chat-send { background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 0 15px; cursor: pointer; font-weight: 500; transition: background 0.2s; }
        #hermes-chat-send:hover { background: #2563eb; }
        #hermes-chat-send:disabled { background: #9ca3af; cursor: not-allowed; }
        #hermes-chat-footer { font-size: 10px; text-align: center; color: #9ca3af; padding: 0 0 10px 0; background: white; }
    `;
    document.head.appendChild(style);

    const container = document.createElement('div');
    container.id = 'hermes-chat-widget';
    container.innerHTML = `
        <div id="hermes-chat-window">
            <div id="hermes-chat-header">
                <div>Live Chat</div>
                <div id="hermes-chat-close">✕</div>
            </div>
            <div id="hermes-chat-messages">
                <div class="hermes-msg hermes-msg-bot">Hello! How can I help you today?</div>
            </div>
            <div id="hermes-chat-input-container">
                <input type="text" id="hermes-chat-input" placeholder="Type your message..." autocomplete="off">
                <button id="hermes-chat-send">Send</button>
            </div>
            <div id="hermes-chat-footer">Powered by SNBD HOST</div>
        </div>
        <button id="hermes-chat-button">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
        </button>
    `;
    document.body.appendChild(container);

    const btn = document.getElementById('hermes-chat-button');
    const win = document.getElementById('hermes-chat-window');
    const closeBtn = document.getElementById('hermes-chat-close');
    const messages = document.getElementById('hermes-chat-messages');
    const input = document.getElementById('hermes-chat-input');
    const sendBtn = document.getElementById('hermes-chat-send');

    let history = [{ role: "system", content: "You are a helpful customer service assistant for this website. Be concise and polite." }];

    btn.addEventListener('click', () => { win.style.display = win.style.display === 'flex' ? 'none' : 'flex'; });
    closeBtn.addEventListener('click', () => { win.style.display = 'none'; });

    function addMsg(txt, isUser) {
        const d = document.createElement('div');
        d.className = 'hermes-msg ' + (isUser ? 'hermes-msg-user' : 'hermes-msg-bot');
        d.innerText = txt;
        messages.appendChild(d);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMsg() {
        const txt = input.value.trim();
        if (!txt) return;
        input.value = '';
        input.disabled = sendBtn.disabled = true;
        addMsg(txt, true);
        history.push({ role: "user", content: txt });
        
        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + API_KEY },
                body: JSON.stringify({ model: "hermes-default", messages: history, temperature: 0.7 })
            });
            const data = await res.json();
            const reply = data.choices[0].message.content;
            addMsg(reply, false);
            history.push({ role: "assistant", content: reply });
        } catch (e) {
            addMsg("Sorry, I am having trouble connecting right now.", false);
        }
        input.disabled = sendBtn.disabled = false;
        input.focus();
    }

    sendBtn.addEventListener('click', sendMsg);
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMsg(); });
{/literal}})();
&lt;/script&gt;
            </textarea>
            
            <button onclick="copyHermesWidgetCode(event)" style="position: absolute; top: 10px; right: 20px; background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: background 0.2s;">
                <i class="far fa-copy"></i> Copy Code
            </button>
        </div>
    </div>
    
    <script>
    function copyHermesWidgetCode(event) {
        var copyText = document.getElementById("hermesWidgetCode");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value).then(function() {
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.style.background = '#10b981';
            setTimeout(function() {
                btn.innerHTML = originalHtml;
                btn.style.background = '#3b82f6';
            }, 2000);
        });
    }
    </script>
    <!-- Hermes Desktop Pairing Guide -->
    <div class="pairing-box">
        <h4 class="pairing-title">
            <i class="fas fa-desktop"></i> Pair with Hermes Desktop App
        </h4>
        <ul class="step-list">
            <li class="step-item">
                <span class="step-number">1</span>
                Download and install the <strong>Hermes Desktop</strong> application for your operating system from the official site (<a href="https://hermes-agent.nousresearch.com/" target="_blank" style="color: #a5b4fc; text-decoration: underline;">hermes-agent.nousresearch.com</a>).
            </li>
            <li class="step-item">
                <span class="step-number">2</span>
                Launch Hermes Desktop, navigate to the connection manager, and select <strong>Remote Gateway</strong> or <strong>Connect to Remote Server</strong>.
            </li>
            <li class="step-item">
                <span class="step-number">3</span>
                Enter the connection settings provided in the card above:
                <br>
                <code style="background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-top: 5px; display: inline-block;">
                    Host/URL: {$dashboard_url}
                </code>
                <br>
                <code style="background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-top: 5px; display: inline-block;">
                    Username: {$username}
                </code>
            </li>
            <li class="step-item">
                <span class="step-number">4</span>
                Save the configurations. Your local Hermes Desktop app is now connected to this high-performance cloud container and will securely save agent states, memory, and custom skills directly on our host.
            </li>
        </ul>
    </div>

    <!-- Data Management -->
    <div class="pairing-box" style="margin-top: 20px;">
        <h4 class="pairing-title">
            <i class="fas fa-database"></i> Data Management
        </h4>
        <p style="font-size: 13px; color: #4b5563; margin-bottom: 15px; line-height: 1.5;">
            Download a complete snapshot of your agent's brain. This backup contains all long-term memories, chat history, custom skills, and environment configurations in a highly compressed `.tar.gz` format.
        </p>
        <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=downloadbackup" class="hermes-btn-primary" style="background: #10b981;">
            <i class="fas fa-download"></i> Download Agent Brain
        </a>
    </div>

    <!-- Danger Zone -->
    <div class="pairing-box" style="margin-top: 20px; border-color: #fca5a5; background: #fef2f2;">
        <h4 class="pairing-title" style="color: #ef4444;">
            <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Danger Zone
        </h4>
        <p style="font-size: 13px; color: #7f1d1d; margin-bottom: 15px; line-height: 1.5;">
            <strong>Warning:</strong> The Kill Switch will completely destroy your Hermes Agent and permanently wipe all its memory, skills, and configuration data. This action cannot be undone.
        </p>
        <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=killswitch" class="hermes-btn-primary" style="background: #dc2626;" onclick="return confirm('WARNING: Are you absolutely sure? All data will be lost forever and the agent will be terminated. Click OK to proceed.');">
            <i class="fas fa-skull-crossbones"></i> Execute Kill Switch
        </a>
    </div>
</div>

<div id="hermes-toast" class="clipboard-toast">Copied to clipboard!</div>

<script>
    function copyToClipboard(text, message) {
        var dummy = document.createElement("textarea");
        document.body.appendChild(dummy);
        dummy.value = text;
        dummy.select();
        document.execCommand("copy");
        document.body.removeChild(dummy);
        
        var toast = document.getElementById("hermes-toast");
        toast.innerText = message;
        toast.classList.add("toast-show");
        
        setTimeout(function() {
            toast.classList.remove("toast-show");
        }, 2500);
    }

    function togglePasswordVisibility() {
        var passField = document.getElementById("dash-pass");
        var btn = document.getElementById("toggle-pass-btn");
        if (passField.style.webkitTextSecurity === "disc" || passField.style.webkitTextSecurity === "") {
            passField.style.webkitTextSecurity = "none";
            btn.innerHTML = '<i class="far fa-eye-slash"></i>';
        } else {
            passField.style.webkitTextSecurity = "disc";
            btn.innerHTML = '<i class="far fa-eye"></i>';
        }
    }

    function toggleApiKeyVisibility() {
        var keyField = document.getElementById("api-key-val");
        var btn = document.getElementById("toggle-api-btn");
        if (keyField.style.webkitTextSecurity === "disc" || keyField.style.webkitTextSecurity === "") {
            keyField.style.webkitTextSecurity = "none";
            btn.innerHTML = '<i class="far fa-eye-slash"></i>';
        } else {
            keyField.style.webkitTextSecurity = "disc";
            btn.innerHTML = '<i class="far fa-eye"></i>';
        }
    }

    // Modern SaaS Overhaul: Remove WHMCS Default Clutter
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Move our beautiful Hermes container to the very top of the tab content
        const hermesContainer = document.querySelector('.hermes-container');
        const tabContent = hermesContainer.closest('.tab-pane') || hermesContainer.closest('.product-details') || hermesContainer.closest('.card-body') || document.querySelector('#Primary_Sidebar')?.parentElement;
        
        if (hermesContainer && tabContent) {
            tabContent.insertBefore(hermesContainer, tabContent.firstChild);
        }

        // 2. Hide all the bulky default WHMCS panels (Service Overview, cPanel, Additional Info)
        const allCards = document.querySelectorAll('.card, .panel, .box, .mb-4, .row');
        allCards.forEach(card => {
            // Don't hide our own container!
            if (card.classList.contains('hermes-container') || card.closest('.hermes-container')) return;
            
            const text = card.innerText.toLowerCase();
            if (
                text.includes('cpanel') || 
                text.includes('service overview') || 
                text.includes('additional information') ||
                text.includes('registration date')
            ) {
                card.style.display = 'none';
            }
        });
        
        // 3. Specifically target images with cpanel logos just in case
        document.querySelectorAll('img[src*="cpanel"]').forEach(img => {
            const wrapper = img.closest('.card') || img.closest('.panel') || img.closest('.row');
            if (wrapper) wrapper.style.display = 'none';
        });
    });
</script>
