<!-- path: app/Views/admin/settings/index.php -->
<?php 
use App\Services\SettingService;
use App\Services\DateService;

$currentTimezone = setting('app_timezone', 'UTC');
$allTimezones    = DateService::getTimezonesList();
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Platform Settings</h2>
            <p class="text-muted">Manage system configuration, mail servers, security, and external services.</p>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Settings have been successfully saved to the database!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form action="/admin/settings/update" method="POST" class="card shadow-sm border-0">
        <!-- Navigation Tabs -->
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs card-header-tabs m-0 px-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#generalTab" role="tab">
                        <i class="bi bi-sliders me-1"></i> General
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#securityTab" role="tab">
                        <i class="bi bi-shield-lock-fill text-danger me-1"></i> Security
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#smtpTab" role="tab">
                        <i class="bi bi-envelope-at me-1"></i> SMTP (Email)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#oauthTab" role="tab">
                        <i class="bi bi-person-badge me-1"></i> OAuth SSO
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#aiTab" role="tab">
                        <i class="bi bi-robot me-1"></i> AI Engine
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#integrationsTab" role="tab">
                        <i class="bi bi-broadcast me-1"></i> Discord & Webhooks
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body tab-content p-4">
            <!-- 1. GENERAL TAB -->
            <div class="tab-pane fade show active" id="generalTab" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Application Brand Name</label>
                        <input type="text" name="app_name" value="<?= htmlspecialchars(setting('app_name', 'My System Status')) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Application Public URL</label>
                        <input type="url" name="app_url" value="<?= htmlspecialchars(setting('app_url', app_url())) ?>" class="form-control" placeholder="https://status.example.com" required>
                        <small class="text-muted">Used for subscriber email verification links and OAuth callbacks.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Default System Timezone (Layer 1)</label>
                        <select name="app_timezone" class="form-select">
                            <?php foreach ($allTimezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= $tz === $currentTimezone ? 'selected' : '' ?>>
                                    <?= $tz ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Fallback timezone when browser auto-detection is not active.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">LibreTranslate API Endpoint</label>
                        <input type="url" name="libretranslate_endpoint" value="<?= htmlspecialchars(setting('libretranslate_endpoint', '')) ?>" class="form-control" placeholder="https://translate.example.com (leave empty if disabled)">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">LibreTranslate API Key (Optional)</label>
                        <input type="password" name="libretranslate_api_key" value="<?= htmlspecialchars(setting('libretranslate_api_key', '')) ?>" class="form-control" placeholder="Optional API Key">
                    </div>
                </div>
            </div>

            <!-- 2. SECURITY TAB (Turnstile & Rate Limiter) -->
            <div class="tab-pane fade" id="securityTab" role="tabpanel">
                <!-- Cloudflare Turnstile Section -->
                <div class="p-4 bg-light rounded-3 border mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Cloudflare Turnstile Bot Protection</h5>
                            <p class="text-muted small mb-0">Protect the admin login form against automated brute-force attacks.</p>
                        </div>
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" name="turnstile_enabled" value="1" id="turnstileSwitch" <?= setting('turnstile_enabled') === '1' ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Turnstile Site Key</label>
                            <input type="text" name="turnstile_site_key" value="<?= htmlspecialchars(setting('turnstile_site_key', '')) ?>" class="form-control font-monospace" placeholder="0x4AAAAAA...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Turnstile Secret Key</label>
                            <input type="password" name="turnstile_secret_key" value="<?= htmlspecialchars(setting('turnstile_secret_key', '')) ?>" class="form-control font-monospace" placeholder="0x4AAAAAA...">
                        </div>
                    </div>
                </div>

                <!-- Rate Limiting Configuration -->
                <div class="p-4 bg-light rounded-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-danger me-2"></i>Anti-Brute Force Rate Limiter</h5>
                            <p class="text-muted small mb-0">Automatically throttle repeated failed login attempts from suspicious IP addresses.</p>
                        </div>
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" name="rate_limit_enabled" value="1" id="rateLimitSwitch" <?= setting('rate_limit_enabled', '1') === '1' ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Max Login Attempts Allowed</label>
                            <input type="number" name="rate_limit_max_attempts" value="<?= htmlspecialchars(setting('rate_limit_max_attempts', '5')) ?>" class="form-control" min="1" max="50">
                            <small class="text-muted">Threshold before temporary IP lockout is triggered.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lockout Duration (Minutes)</label>
                            <input type="number" name="rate_limit_lockout_minutes" value="<?= htmlspecialchars(setting('rate_limit_lockout_minutes', '15')) ?>" class="form-control" min="1" max="1440">
                            <small class="text-muted">Minutes the attacker IP must wait before retrying.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. SMTP (EMAIL) TAB -->
            <div class="tab-pane fade" id="smtpTab" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars(setting('smtp_host', '')) ?>" class="form-control" placeholder="smtp.example.com (leave empty if disabled)">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars(setting('smtp_port', '587')) ?>" class="form-control" placeholder="587">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?= htmlspecialchars(setting('smtp_user', '')) ?>" class="form-control" placeholder="user@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMTP Password</label>
                        <input type="password" name="smtp_pass" value="<?= htmlspecialchars(setting('smtp_pass', '')) ?>" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Encryption Protocol</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="starttls" <?= setting('smtp_encryption', 'starttls') === 'starttls' ? 'selected' : '' ?>>STARTTLS (Default Port 587)</option>
                            <option value="ssl" <?= setting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL / TLS (Port 465)</option>
                            <option value="none" <?= setting('smtp_encryption') === 'none' ? 'selected' : '' ?>>None (Plain Port 25)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">From Email Address</label>
                        <input type="email" name="smtp_from" value="<?= htmlspecialchars(setting('smtp_from', '')) ?>" class="form-control" placeholder="noreply@example.com">
                    </div>
                </div>
            </div>

            <!-- 4. OAUTH SSO TAB -->
            <div class="tab-pane fade" id="oauthTab" role="tabpanel">
                <!-- MYETV SSO -->
                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <h5 class="fw-bold text-primary mb-2"><i class="bi bi-tv me-2"></i>MYETV SSO Provider</h5>
                    <p class="small text-muted mb-3">API integration from <code>https://developers.myetv.tv</code></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">MYETV Client ID</label>
                            <input type="text" name="oauth_myetv_client_id" value="<?= htmlspecialchars(setting('oauth_myetv_client_id', '')) ?>" class="form-control" placeholder="Leave empty to disable">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">MYETV Client Secret</label>
                            <input type="password" name="oauth_myetv_client_secret" value="<?= htmlspecialchars(setting('oauth_myetv_client_secret', '')) ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Google SSO -->
                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <h5 class="fw-bold text-danger mb-2"><i class="bi bi-google me-2"></i>Google OAuth 2.0</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Google Client ID</label>
                            <input type="text" name="oauth_google_client_id" value="<?= htmlspecialchars(setting('oauth_google_client_id', '')) ?>" class="form-control" placeholder="Leave empty to disable">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Google Client Secret</label>
                            <input type="password" name="oauth_google_client_secret" value="<?= htmlspecialchars(setting('oauth_google_client_secret', '')) ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Microsoft Azure AD SSO -->
                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <h5 class="fw-bold text-info mb-2"><i class="bi bi-microsoft me-2"></i>Microsoft Azure AD</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Microsoft Application (Client) ID</label>
                            <input type="text" name="oauth_microsoft_client_id" value="<?= htmlspecialchars(setting('oauth_microsoft_client_id', '')) ?>" class="form-control" placeholder="Leave empty to disable">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Microsoft Client Secret</label>
                            <input type="password" name="oauth_microsoft_client_secret" value="<?= htmlspecialchars(setting('oauth_microsoft_client_secret', '')) ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Facebook SSO -->
                <div class="p-3 bg-light rounded-3 border">
                    <h5 class="fw-bold text-primary mb-2"><i class="bi bi-facebook me-2"></i>Facebook Login</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Facebook App ID</label>
                            <input type="text" name="oauth_facebook_client_id" value="<?= htmlspecialchars(setting('oauth_facebook_client_id', '')) ?>" class="form-control" placeholder="Leave empty to disable">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook App Secret</label>
                            <input type="password" name="oauth_facebook_client_secret" value="<?= htmlspecialchars(setting('oauth_facebook_client_secret', '')) ?>" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. AI ENGINE TAB -->
            <div class="tab-pane fade" id="aiTab" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">AI Provider Engine</label>
                        <select name="ai_provider" class="form-select">
                            <option value="gemini" <?= setting('ai_provider', 'gemini') === 'gemini' ? 'selected' : '' ?>>Google Gemini API</option>
                            <option value="ollama" <?= setting('ai_provider') === 'ollama' ? 'selected' : '' ?>>Ollama (Local / Self-Hosted)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Model Name</label>
                        <input type="text" name="ai_model" value="<?= htmlspecialchars(setting('ai_model', '')) ?>" class="form-control" placeholder="e.g. gemini-1.5-flash or llama3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gemini API Key</label>
                        <input type="password" name="ai_api_key" value="<?= htmlspecialchars(setting('ai_api_key', '')) ?>" class="form-control" placeholder="AIzaSy... (leave empty if not using Gemini)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ollama Endpoint URL</label>
                        <input type="url" name="ai_endpoint" value="<?= htmlspecialchars(setting('ai_endpoint', '')) ?>" class="form-control" placeholder="e.g. http://127.0.0.1:11434 (leave empty if not using Ollama)">
                    </div>
                </div>
            </div>

            <!-- 6. INTEGRATIONS TAB -->
            <div class="tab-pane fade" id="integrationsTab" role="tabpanel">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="bi bi-discord text-primary me-1"></i> Discord Channel Webhook URL</label>
                    <input type="url" name="discord_webhook_url" value="<?= htmlspecialchars(setting('discord_webhook_url', '')) ?>" class="form-control" placeholder="https://discord.com/api/webhooks/... (leave empty to disable)">
                    <small class="text-muted">Probes will post down/up alert embeds directly to this Discord channel.</small>
                </div>

                <!-- Optional Cloudflare Zero Trust Tunnel Section in app/Views/admin/settings/index.php -->
<div class="p-4 bg-light rounded-3 border mb-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="p-2 bg-warning bg-opacity-10 text-dark rounded-3 fs-4">
            <i class="bi bi-shield-shaded"></i>
        </span>
        <div>
            <h5 class="fw-bold mb-0">Cloudflare Zero Trust Tunnel Monitor (Optional)</h5>
            <p class="text-muted small mb-0">Monitor the live health status of your private Cloudflare Tunnel (cloudflared).</p>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Custom Tunnel Label</label>
            <input type="text" name="cf_tunnel_name" value="<?= htmlspecialchars(setting('cf_tunnel_name', '')) ?>" class="form-control" placeholder="e.g. Production Core Tunnel">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Cloudflare Account ID</label>
            <input type="text" name="cf_tunnel_account_id" value="<?= htmlspecialchars(setting('cf_tunnel_account_id', '')) ?>" class="form-control font-monospace" placeholder="32-character account ID">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Tunnel ID (UUID)</label>
            <input type="text" name="cf_tunnel_id" value="<?= htmlspecialchars(setting('cf_tunnel_id', '')) ?>" class="form-control font-monospace" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Cloudflare API Token</label>
            <input type="password" name="cf_tunnel_api_token" value="<?= htmlspecialchars(setting('cf_tunnel_api_token', '')) ?>" class="form-control font-monospace" placeholder="API Token with Tunnel:Read permission">
            <small class="text-muted">Create a token in Cloudflare Dashboard with <code>Account &gt; Cloudflare Tunnel &gt; Read</code> permissions.</small>
        </div>
    </div>
</div>
            </div>
        </div>

        <div class="card-footer bg-light p-3 text-end">
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                <i class="bi bi-save me-1"></i> Save Platform Settings
            </button>
        </div>
    </form>
</div>
