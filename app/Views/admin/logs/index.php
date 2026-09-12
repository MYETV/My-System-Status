<!-- path: app/Views/admin/logs/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">System Audit & Probe Logs</h2>
            <p class="text-muted">Last 100 probe executions, heartbeat response times, and failure diagnostics.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Monitor</th>
                        <th>Status</th>
                        <th>Latency</th>
                        <th>HTTP Code</th>
                        <th>Diagnostic Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($probeLogs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No logs recorded yet.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($probeLogs as $log): ?>
                        <tr>
                            <td><small class="text-muted"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></small></td>
                            <td class="fw-semibold"><?= htmlspecialchars($log['monitor_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $log['status'] === 'up' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($log['status']) ?>
                                </span>
                            </td>
                            <td><?= $log['response_time_ms'] ?> ms</td>
                            <td><code><?= $log['http_code'] ?? '-' ?></code></td>
                            <td><small class="text-danger"><?= htmlspecialchars($log['error_message'] ?? 'OK') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>