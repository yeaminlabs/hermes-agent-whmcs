<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    .hermes-container {
        font-family: 'Outfit', sans-serif;
        background: radial-gradient(120% 120% at 50% 0%, #151821 0%, #0d0e12 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 30px;
        color: #f3f4f6;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        margin: 20px 0;
        position: relative;
    }
    .hermes-container::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: linear-gradient(90deg, #e11d48, #f43f5e, #fda4af);
    }
    .snbd-title {
        font-size: 24px; font-weight: 700; margin: 0 0 5px 0;
        color: #fff; display: flex; align-items: center; gap: 10px;
    }
    .snbd-title i { color: #f43f5e; }
    .snbd-subtitle { font-size: 14px; color: #9ca3af; margin-bottom: 25px; }
    
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #d1d5db; }
    .form-control-custom {
        width: 100%; padding: 12px 15px; border-radius: 8px;
        background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff; font-family: monospace; font-size: 14px; transition: all 0.2s;
        box-sizing: border-box;
    }
    .form-control-custom:focus {
        outline: none; border-color: #f43f5e; box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.2);
    }
    .btn-submit {
        background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%); color: white;
        border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;
        font-size: 15px; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4); transition: all 0.2s;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(225, 29, 72, 0.6); color: white; text-decoration: none; }
    .btn-back {
        background: rgba(255, 255, 255, 0.05); color: #d1d5db; border: 1px solid rgba(255,255,255,0.1);
        padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;
        text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;
    }
    .btn-back:hover { background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none; }
    .btn-container { display: flex; gap: 15px; margin-top: 30px; }
    .alert-success-custom { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-error-custom { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .card-section { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 25px; margin-bottom: 20px; }
    .section-title { font-size: 16px; font-weight: 600; color: #fff; margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
</style>

<div class="hermes-container">
    <h2 class="snbd-title"><i class="fas fa-microchip"></i> Manage LLM Providers</h2>
    <p class="snbd-subtitle">Add multiple API keys and seamlessly swap your active model powered by SNBD HOST.</p>

    {if $success}
        <div class="alert-success-custom"><i class="fas fa-check-circle"></i> LLM Configuration updated! Your Hermes Agent has been restarted and is now using the new settings.</div>
    {/if}
    {if $error}
        <div class="alert-error-custom"><i class="fas fa-exclamation-triangle"></i> {$error}</div>
    {/if}

    <form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=update_llm">
        <div class="card-section">
            <h3 class="section-title"><i class="fas fa-brain" style="color: #f43f5e;"></i> Active Model</h3>
            <div class="form-group">
                <label class="form-label">Model String</label>
                <input type="text" name="active_model" class="form-control-custom" value="{$active_model}" placeholder="e.g. openrouter/meta-llama/llama-3-70b-instruct">
                <small style="color: #9ca3af; display: block; margin-top: 5px;">This specifies exactly which model your agent should use.</small>
            </div>
        </div>

        <div class="card-section">
            <h3 class="section-title"><i class="fas fa-key" style="color: #f43f5e;"></i> API Keys (Add as many as you want)</h3>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    OpenRouter API Key
                </label>
                <input type="password" name="openrouter_key" class="form-control-custom" value="{$openrouter_key}" placeholder="sk-or-v1-...">
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
                    OpenAI API Key
                </label>
                <input type="password" name="openai_key" class="form-control-custom" value="{$openai_key}" placeholder="sk-...">
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Anthropic API Key
                </label>
                <input type="password" name="anthropic_key" class="form-control-custom" value="{$anthropic_key}" placeholder="sk-ant-...">
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Nous Portal API Key
                </label>
                <input type="password" name="nous_key" class="form-control-custom" value="{$nous_key}" placeholder="...">
            </div>
        </div>
        
        <div class="card-section">
            <h3 class="section-title"><i class="fab fa-telegram-plane" style="color: #f43f5e;"></i> Messaging Channels</h3>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fab fa-telegram" style="color: #0088cc; font-size: 16px;"></i> Telegram Bot Token
                </label>
                <input type="password" name="telegram_token" class="form-control-custom" value="{$telegram_token}" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ">
                <small style="color: #9ca3af; display: block; margin-top: 5px;">Get this from BotFather on Telegram to allow your agent to talk via Telegram.</small>
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                    <i class="fab fa-discord" style="color: #5865F2; font-size: 16px;"></i> Discord Bot Token
                </label>
                <input type="password" name="discord_token" class="form-control-custom" value="{$discord_token}" placeholder="MTEyMzQ1Njc4OTA.abcDEF.1234567890abcdef">
            </div>
        </div>
        
        <div class="card-section">
            <h3 class="section-title"><i class="fas fa-server" style="color: #f43f5e;"></i> Custom OpenAI-Compatible Endpoint</h3>
            <div class="form-group">
                <label class="form-label">Custom API Base URL</label>
                <input type="text" name="custom_url" class="form-control-custom" value="{$custom_url}" placeholder="https://api.yourdomain.com/v1">
            </div>
            <div class="form-group">
                <label class="form-label">Custom API Key</label>
                <input type="password" name="custom_key" class="form-control-custom" value="{$custom_key}" placeholder="Custom Key (if required)">
            </div>
        </div>

        <div class="btn-container">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save & Restart Agent</button>
            <a href="clientarea.php?action=productdetails&id={$serviceid}" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </form>
</div>
