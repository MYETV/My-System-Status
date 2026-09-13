<!-- path: app/Views/admin/plugins/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Plugins & Integrations</h2>
            <p class="text-muted">Connect external status feeds, notification channels, and AI engines.</p>
        </div>
        <form action="/admin/plugins/sync-feeds" method="POST">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-repeat me-1"></i> Sync External Feeds Now
            </button>
        </form>
    </div>

    <!-- Feeds Synced Alert -->
    <?php if (isset($_GET['synced'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> External status feeds synchronized successfully!
            <div class="small mt-1"><strong>Status:</strong> <?= htmlspecialchars($_GET['summary'] ?? '') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Translation Success Alert -->
    <?php if (isset($_GET['translated'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'Translations updated successfully!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Error Alert -->
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Error: <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- 1. External Status Feeds Importer with Selection Checkboxes -->
<div class="col-md-6 col-xl-6">
    <form action="/admin/plugins/save-feeds-config" method="POST" class="card h-100 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 fs-4">
                        <i class="bi bi-cloud-arrow-down"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0">External Status Importer</h5>
                        <span class="badge bg-success">Multi-Provider</span>
                    </div>
                </div>
            </div>
            <p class="text-muted small mb-3">
                Choose which public status pages to poll and include in your status dashboard:
            </p>

            <!-- Provider Selection Checkboxes -->
            <div class="bg-light p-3 rounded-3 border mb-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="feed_cloudflare_enabled" value="1" id="feedCf" <?= setting('feed_cloudflare_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="feedCf">
                        Cloudflare (Includes Workers, DNS, CDN & Dashboard sub-services)
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="feed_stripe_enabled" value="1" id="feedStripe" <?= setting('feed_stripe_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="feedStripe">
                        Stripe Payments Engine
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="feed_github_enabled" value="1" id="feedGh" <?= setting('feed_github_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="feedGh">
                        GitHub Cloud Services
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="feed_paypal_enabled" value="1" id="feedPaypal" <?= setting('feed_paypal_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="feedPaypal">
                        PayPal Payments Infrastructure
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white border-0 pt-0 pb-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-save me-1"></i> Save & Poll Enabled Feeds
            </button>
        </div>
    </form>
</div>

        <!-- 2. Discord Webhook Plugin -->
        <div class="col-md-6 col-xl-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-indigo bg-opacity-10 text-primary rounded-3 fs-4">
                                <i class="bi bi-discord"></i>
                            </span>
                            <div>
                                <h5 class="fw-bold mb-0">Discord Alert Webhooks</h5>
                                <span class="badge bg-<?= $discordConfigured ? 'success' : 'secondary' ?>">
                                    <?= $discordConfigured ? 'Configured' : 'Not Configured' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Dispatches rich message embeds directly into your designated Discord channel whenever an endpoint goes down or an incident is declared.
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3">
                    <a href="/admin/settings#integrationsTab" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i> Configure Webhook
                    </a>
                </div>
            </div>
        </div>

        <!-- 3. AI Incident Assistant (Ollama & Gemini) -->
        <div class="col-md-6 col-xl-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-danger bg-opacity-10 text-danger rounded-3 fs-4">
                                <i class="bi bi-robot"></i>
                            </span>
                            <div>
                                <h5 class="fw-bold mb-0">AI Incident Assistant</h5>
                                <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($aiProvider) ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Drafts clear, user-friendly incident post-mortems and status updates by analyzing raw infrastructure stack traces and probe error logs using <strong><?= htmlspecialchars($aiModel) ?></strong>.
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3">
                    <a href="/admin/settings#aiTab" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i> Configure AI Model
                    </a>
                </div>
            </div>
        </div>

        <!-- 4. LibreTranslate Auto-Translator -->
        <div class="col-md-6 col-xl-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-warning bg-opacity-10 text-dark rounded-3 fs-4">
                                <i class="bi bi-translate"></i>
                            </span>
                            <div>
                                <h5 class="fw-bold mb-0">LibreTranslate i18n</h5>
                                <span class="badge bg-<?= !empty(setting('libretranslate_endpoint')) ? 'success' : 'secondary' ?>">
                                    <?= !empty(setting('libretranslate_endpoint')) ? 'Ready' : 'Not Configured' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Automatically synchronizes and translates master keys from <code>en.json</code> into other target languages using your connected LibreTranslate instance (<code><?= htmlspecialchars($translateEndpoint ?: 'Not configured') ?></code>).
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#translateModal">
                        <i class="bi bi-translate me-1"></i> Auto-Translate JSONs
                    </button>
                    <a href="/admin/settings#generalTab" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i> Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Auto-Translate with LibreTranslate -->
<div class="modal fade" id="translateModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/translations/sync" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-translate me-2 text-primary"></i>LibreTranslate Auto-Sync</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    This tool reads <code>languages/en.json</code>, identifies missing keys in the target language file, translates them via your LibreTranslate server, and saves the updated JSON file.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Target Language Code (ISO 639-1)</label>
                    <select name="target_lang" class="form-select" required>
                        <option value="it">Italian (it.json)</option>
                        <option value="es">Spanish (es.json)</option>
                        <option value="fr">French (fr.json)</option>
                        <option value="de">German (de.json)</option>
                        <option value="pt">Portuguese (pt.json)</option>
                    </select>
                    <small class="text-muted">If the language file does not exist, it will be automatically created.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-magic me-1"></i> Start Auto-Translation
                </button>
            </div>
        </form>
    </div>
</div>
