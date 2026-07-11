<!-- Google Fonts Integration -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    /* Aggressively hide default WHMCS theme elements that clutter the page */
    div[id="cPanelConnect"], 
    .cpanel-feature,
    .panel-cpanel {
        display: none !important;
    }
    
    .hermes-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: radial-gradient(120% 120% at 50% 0%, #151821 0%, #0d0e12 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 30px;
        color: #f3f4f6;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        margin: 20px 0;
        position: relative;
        overflow: hidden;
    }

    .hermes-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
    }

    .hermes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding-bottom: 20px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .hermes-logo-area {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hermes-logo-wrapper {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        filter: drop-shadow(0 0 8px rgba(225, 29, 72, 0.5));
    }

    .hermes-logo-icon {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #f43f5e 0%, #ffffff 100%);
        -webkit-mask: url(https://raw.githubusercontent.com/lobehub/lobe-icons/refs/heads/master/packages/static-png/light/hermesagent.png) no-repeat center / contain;
        mask: url(https://raw.githubusercontent.com/lobehub/lobe-icons/refs/heads/master/packages/static-png/light/hermesagent.png) no-repeat center / contain;
    }

    .hermes-title {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(to right, #ffffff, #d1d5db);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hermes-subtitle {
        font-size: 13px;
        color: #9ca3af;
        margin: 2px 0 0 0;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    .status-active {
        background-color: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #34d399;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.15);
    }

    .status-pending {
        background-color: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #fbbf24;
        box-shadow: 0 0 15px rgba(245, 158, 11, 0.15);
    }

    .status-suspended {
        background-color: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
        box-shadow: 0 0 15px rgba(239, 68, 68, 0.15);
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

    .hermes-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
    }

    @media (max-width: 768px) {
        .hermes-grid {
            grid-template-columns: 1fr;
        }
    }

    .hermes-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .hermes-card:hover {
        border-color: rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.04);
    }

    .card-title {
        font-size: 15px;
        font-weight: 600;
        color: #ffffff;
        margin-top: 0;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-title i {
        color: #818cf8;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .info-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-label {
        font-size: 13px;
        color: #9ca3af;
    }

    .info-value {
        font-size: 13px;
        font-weight: 500;
        color: #e5e7eb;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .code-val {
        font-family: 'Fira Code', monospace;
        background: rgba(0, 0, 0, 0.3);
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 12px;
    }

    .btn-icon-only {
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .btn-icon-only:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.1);
    }

    .hermes-btn-primary {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
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
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .hermes-btn-primary:hover {
        opacity: 0.95;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.5);
        color: white;
        text-decoration: none;
    }

    /* Step-by-Step pairing box */
    .pairing-box {
        background: rgba(99, 102, 241, 0.03);
        border: 1px solid rgba(99, 102, 241, 0.15);
        border-radius: 12px;
        padding: 22px;
        margin-top: 10px;
    }

    .pairing-title {
        font-size: 16px;
        font-weight: 600;
        color: #a5b4fc;
        margin-top: 0;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .step-list {
        list-style-type: none;
        padding-left: 0;
        margin: 0;
    }

    .step-item {
        position: relative;
        padding-left: 30px;
        margin-bottom: 12px;
        font-size: 13.5px;
        line-height: 1.5;
        color: #d1d5db;
    }

    .step-item:last-child {
        margin-bottom: 0;
    }

    .step-number {
        position: absolute;
        left: 0;
        top: 2px;
        width: 20px;
        height: 20px;
        background: rgba(99, 102, 241, 0.15);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #a5b4fc;
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
    <div class="hermes-header">
        <div class="hermes-logo-area">
            <div class="hermes-logo-wrapper">
                <div class="hermes-logo-icon"></div>
            </div>
            <div>
                <h3 class="hermes-title">Hermes Cloud Agent</h3>
                <p class="hermes-subtitle">Managed dockerized deployment by SNBD Host</p>
            </div>
        </div>
        <div>
            {if $deployment_status eq 'Active'}
                <span class="status-badge status-active" style="margin-right: 15px;">
                    <span class="status-dot"></span>
                    Running
                </span>
                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=manage_llm" class="hermes-btn-primary" style="background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%);">
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

    <div class="hermes-grid">
        <!-- Dashboard Credentials Card -->
        <div class="hermes-card">
            <h4 class="card-title">
                <i class="fas fa-sliders-h"></i> Gateway Dashboard Connection
            </h4>
            
            <div class="info-row">
                <span class="info-label">Web Dashboard URL</span>
                <span class="info-value">
                    {if $dashboard_url}
                        <a href="{$dashboard_url}" target="_blank" class="code-val" style="color: #818cf8; text-decoration: none;">
                            {$dashboard_url} <i class="fas fa-external-link-alt" style="font-size: 10px;"></i>
                        </a>
                        <button class="btn-icon-only" onclick="copyToClipboard('{$dashboard_url}', 'Dashboard URL copied!')" title="Copy URL">
                            <i class="far fa-copy"></i>
                        </button>
                    {else}
                        <span class="text-muted">Not Available</span>
                    {/if}
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Auth Username</span>
                <span class="info-value">
                    <span class="code-val">{$username}</span>
                    <button class="btn-icon-only" onclick="copyToClipboard('{$username}', 'Username copied!')" title="Copy Username">
                        <i class="far fa-copy"></i>
                    </button>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Auth Password</span>
                <span class="info-value">
                    <span class="code-val" id="dash-pass" style="-webkit-text-security: disc;">{$password}</span>
                    <button class="btn-icon-only" onclick="togglePasswordVisibility()" title="Toggle View" id="toggle-pass-btn">
                        <i class="far fa-eye"></i>
                    </button>
                    <button class="btn-icon-only" onclick="copyToClipboard('{$password}', 'Password copied!')" title="Copy Password">
                        <i class="far fa-copy"></i>
                    </button>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Dashboard Port</span>
                <span class="info-value">
                    <span class="code-val">{$dash_port}</span>
                </span>
            </div>

            <div style="margin-top: 15px; text-align: right;">
                {if $dashboard_url}
                    <a href="{$dashboard_url}" target="_blank" class="hermes-btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Open Dashboard
                    </a>
                {/if}
            </div>
        </div>

        <!-- OpenAI Compatibility API Card -->
        <div class="hermes-card">
            <h4 class="card-title">
                <i class="fas fa-code"></i> OpenAI-Compatible API Server
            </h4>
            {if $api_enabled}
                <div class="info-row">
                    <span class="info-label">Base URL (v1)</span>
                    <span class="info-value text-right" style="max-width: 70%;">
                        <span class="code-val" style="word-break: break-all;">{$api_url}</span>
                        <button class="btn-icon-only" onclick="copyToClipboard('{$api_url}', 'API Base URL copied!')" title="Copy URL">
                            <i class="far fa-copy"></i>
                        </button>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">API Key</span>
                    <span class="info-value">
                        <span class="code-val" id="api-key-val" style="-webkit-text-security: disc;">{$api_key}</span>
                        <button class="btn-icon-only" onclick="toggleApiKeyVisibility()" title="Toggle View" id="toggle-api-btn">
                            <i class="far fa-eye"></i>
                        </button>
                        <button class="btn-icon-only" onclick="copyToClipboard('{$api_key}', 'API Key copied!')" title="Copy Key">
                            <i class="far fa-copy"></i>
                        </button>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">API Port</span>
                    <span class="info-value">
                        <span class="code-val">{$api_port}</span>
                    </span>
                </div>
                <p style="font-size: 11.5px; color: #9ca3af; margin: 10px 0 0 0; line-height: 1.4;">
                    <i class="fas fa-info-circle" style="color: #818cf8;"></i> Exposes <code>/v1/chat/completions</code>. Use this key to plug your remote agent directly into third-party UI managers like Open WebUI or LobeChat.
                </p>
            {else}
                <div style="display: flex; height: 80%; flex-direction: column; justify-content: center; align-items: center; color: #6b7280; text-align: center; padding: 20px 0;">
                    <i class="fas fa-ban" style="font-size: 30px; margin-bottom: 10px;"></i>
                    <p style="font-size: 13px; margin: 0;">OpenAI-Compatible API is disabled.</p>
                    <p style="font-size: 11px; margin-top: 4px; max-width: 80%;">Enable this feature during checkout or via package configuration upgrade to route third-party tools.</p>
                </div>
            {/if}
        </div>
    </div>

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
