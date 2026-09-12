<!-- path: app/Views/public/index.php -->
<div class="container my-5" style="max-width: 900px;">
    <!-- Global Status Banner -->
    <?php 
        $badgeClass = match ($overallStatus) {
            'operational'  => 'bg-success',
            'degraded'     => 'bg-warning text-dark',
            'major_outage' => 'bg-danger',
            default        => 'bg-secondary'
        };
        $statusText = match ($overallStatus) {
            'operational'  => __('status.operational'),
            'degraded'     => __('status.degraded'),
            'major_outage' => __('status.major_outage'),
            default        => 'Unknown'
        };
    ?>
    <div class="p-4 rounded-3 text-white <?= $badgeClass ?> shadow-sm mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-shield-fill-check me-2"></i> <?= $statusText ?></h4>
        <button class="btn btn-light btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#subscribeModal">
            <i class="bi bi-bell me-1"></i> Subscribe to Updates
        </button>
    </div>

    <!-- Active Maintenances -->
    <?php if (!empty($maintenances)): ?>
        <div class="card border-info mb-4 shadow-sm">
            <div class="card-header bg-info text-dark fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools me-2"></i> <?= __('maintenance.title') ?></span>
                <small class="badge bg-dark bg-opacity-25 text-white"><i class="bi bi-clock me-1"></i> <?= \App\Services\DateService::getActiveTimezone() ?></small>
            </div>
            <div class="card-body">
                <?php foreach ($maintenances as $maint): ?>
                    <h5 class="card-title"><?= htmlspecialchars($maint['title']) ?></h5>
                    <p class="card-text text-muted"><?= nl2br(htmlspecialchars($maint['description'])) ?></p>
                    <small class="badge bg-secondary">
                        <time datetime="<?= htmlspecialchars($maint['start_time']) ?>">
                            <?= format_date($maint['start_time'], 'M d, H:i') ?>
                        </time>
                        &mdash;
                        <time datetime="<?= htmlspecialchars($maint['end_time']) ?>">
                            <?= format_date($maint['end_time'], 'M d, H:i T') ?>
                        </time>
                    </small>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Incidents -->
    <?php if (!empty($incidents)): ?>
        <h5 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> Active Incidents</h5>
        <?php foreach ($incidents as $incident): ?>
            <div class="card border-danger mb-3 shadow-sm">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($incident['title']) ?></strong>
                    <span class="badge bg-light text-danger text-uppercase"><?= $incident['status'] ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($incident['ai_summary'])): ?>
                        <div class="alert alert-light border mb-3">
                            <small class="text-muted d-block fw-bold mb-1"><i class="bi bi-robot me-1"></i> AI Incident Summary</small>
                            <?= nl2br(htmlspecialchars($incident['ai_summary'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="timeline ps-3 border-start">
                        <?php foreach ($incident['updates'] as $update): ?>
                            <div class="mb-3 position-relative">
                                <span class="badge bg-secondary">
                                    <time datetime="<?= htmlspecialchars($update['created_at']) ?>">
                                        <?= format_date($update['created_at'], 'M d, H:i') ?>
                                    </time>
                                </span>
                                <strong class="ms-2 text-capitalize"><?= $update['status'] ?>:</strong>
                                <p class="mb-0 text-muted mt-1"><?= nl2br(htmlspecialchars($update['message'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Services / Monitors List -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Services & Infrastructure</h5>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($monitors as $monitor): ?>
                <li class="list-group-item py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-6"><?= htmlspecialchars($monitor['name']) ?></span>
                        <span class="badge <?= $monitor['current_status'] === 'operational' ? 'bg-success' : 'bg-danger' ?>">
                            <?= strtoupper($monitor['current_status']) ?>
                        </span>
                    </div>

                    <!-- 90 Days Uptime Simulation Bar -->
                    <div class="d-flex gap-1" style="height: 24px;" title="90 Days Uptime: <?= $monitor['uptime_percentage'] ?>%">
                        <?php for ($i = 0; $i < 45; $i++): ?>
                            <div class="flex-grow-1 rounded-1 <?= $monitor['current_status'] === 'operational' ? 'bg-success' : 'bg-danger' ?>" style="opacity: <?= rand(80, 100) / 100 ?>;"></div>
                        <?php endfor; ?>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-1">
                        <span>90 days ago</span>
                        <span><?= $monitor['uptime_percentage'] ?>% uptime</span>
                        <span>Today</span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/subscribe" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bell me-2"></i> Subscribe to Incident Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Get notifications directly to your inbox whenever an incident or scheduled maintenance occurs.</p>
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary">Subscribe</button>
</div>