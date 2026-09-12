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

    <?php if (isset($_GET['synced'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> External status feeds synchronized successfully!
            <div class="small mt-1"><strong>Status:</strong> <?= htmlspecialchars($_GET['summary'] ?? '') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- 1. External Status Feeds Importer -->
        <div class="col-md-6 col-xl-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 fs-4">
                                <i class="bi bi-cloud-arrow-down"></i>
                            </span>
                            <div>
                                <h5 class="fw-bold mb-0">External Status Importer</h5>
                                <span class="badge bg-success">Active &bull; Built-in</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Automatically imports and synchronizes health status from major third-party cloud providers (<strong>Cloudflare</strong>, <strong>GitHub</strong>, <strong>Stripe</strong>) into your public monitors list.
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3">
                    <form action="/admin/plugins/sync-feeds" method="POST" class="d-inline">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Poll Feeds Now
                        </button>
                    </form>
                </div>
            </div>
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
                                <span class="badge bg-info text-dark">Automated</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Synchronizes and automatically translates master language keys from <code>en.json</code> into other target languages using your connected instance (<code><?= htmlspecialchars($translateEndpoint) ?></code>).
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3">
                    <a href="/admin/settings#generalTab" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i> Configure Endpoint
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>