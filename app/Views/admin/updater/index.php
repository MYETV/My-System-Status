<!-- path: app/Views/admin/updater/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Software Updates</h2>
            <p class="text-muted">Keep My System Status up-to-date with official releases from GitHub.</p>
        </div>
        <a href="/admin/updater" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i> Check Again
        </a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <strong>Congratulations!</strong> My System Status has been successfully updated to the latest version.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Update failed: <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Version Status Card -->
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 fs-3">
                            <i class="bi bi-tag-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Installed Version</div>
                            <h3 class="fw-bold mb-0">v<?= htmlspecialchars($currentVersion) ?></h3>
                        </div>
                    </div>

                    <?php if ($updateInfo && ($updateInfo['has_update'] ?? false)): ?>
                        <div class="alert alert-warning mb-0 py-2 small">
                            <i class="bi bi-arrow-up-circle-fill me-1"></i> A newer version (<strong>v<?= htmlspecialchars($updateInfo['version']) ?></strong>) is available!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0 py-2 small">
                            <i class="bi bi-shield-check me-1"></i> You are running the latest version.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Release Details Card -->
        <div class="col-md-6 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-github me-2"></i>GitHub Release Channel</h5>
                </div>
                <div class="card-body">
                    <?php if ($updateInfo === null): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-cloud-slash fs-1 d-block mb-2 text-secondary"></i>
                            <h6>No remote release found</h6>
                            <p class="small mb-0">Either the GitHub repository is private, offline, or has no published releases yet.</p>
                        </div>
                    <?php elseif ($updateInfo['has_update']): ?>
                        <h5 class="text-primary fw-bold">New Release: v<?= htmlspecialchars($updateInfo['version']) ?></h5>
                        <p class="text-muted small mb-2">Published: <?= format_date($updateInfo['published_at'], 'M d, Y H:i') ?></p>
                        
                        <div class="p-3 bg-light rounded-3 border mb-3 small" style="max-height: 200px; overflow-y: auto;">
                            <?= nl2br(htmlspecialchars($updateInfo['release_notes'])) ?>
                        </div>

                        <form action="/admin/updater/apply" method="POST" onsubmit="return confirm('Start the update process now? Files will be updated automatically.');">
                            <input type="hidden" name="zip_url" value="<?= htmlspecialchars($updateInfo['zip_url']) ?>">
                            <button type="submit" class="btn btn-success fw-bold px-4">
                                <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download & Install v<?= htmlspecialchars($updateInfo['version']) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                            <h5 class="fw-bold">Platform is Up to Date</h5>
                            <p class="text-muted small mb-0">No action is required. Probes and services are running on the latest stable code.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>