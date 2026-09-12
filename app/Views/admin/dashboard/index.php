<!-- path: app/Views/admin/dashboard/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Infrastructure Overview</h2>
            <p class="text-muted">Real-time status of your services, probes, and subscribers.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/monitors" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Add Route</a>
            <a href="/admin/incidents" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i> Declare Outage</a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 fs-3">
                        <i class="bi bi-hdd-network"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Monitors</div>
                        <h3 class="fw-bold mb-0"><?= $monitorsTotal ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 fs-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Operational</div>
                        <h3 class="fw-bold mb-0"><?= $monitorsUp ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Incidents</div>
                        <h3 class="fw-bold mb-0"><?= $activeIncidents ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 fs-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Subscribers</div>
                        <h3 class="fw-bold mb-0"><?= $subscribersCount ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Probes Activity -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-activity text-primary me-2"></i>Latest Heartbeat Checks</h5>
            <a href="/admin/logs" class="btn btn-sm btn-light">View All Logs</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Time</th>
                        <th>Monitor</th>
                        <th>Status</th>
                        <th>Latency</th>
                        <th>HTTP Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No heartbeat checks recorded yet. Run the cron runner to begin.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><small class="text-muted"><?= format_date($log['created_at'], 'H:i:s') ?></small></td>
                            <td class="fw-semibold"><?= htmlspecialchars($log['monitor_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $log['status'] === 'up' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($log['status']) ?>
                                </span>
                            </td>
                            <td><?= $log['response_time_ms'] ?> ms</td>
                            <td><code><?= $log['http_code'] ?? '-' ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>