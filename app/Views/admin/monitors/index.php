<!-- path: app/Views/admin/monitors/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Monitors & Services</h2>
            <p class="text-muted">Manage the endpoints and services monitored by probes.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMonitorModal">
            <i class="bi bi-plus-lg me-1"></i> Add New Route
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Interval</th>
                        <th>Last Check</th>
                        <th>Uptime</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitors)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No monitors found. Add your first route!</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($monitors as $m): ?>
                        <tr>
                            <td>
                                <?php if ($m['current_status'] === 'operational'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> UP</span>
                                <?php elseif ($m['current_status'] === 'down'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> DOWN</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> <?= strtoupper($m['current_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($m['name']) ?></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= $m['type'] ?></span></td>
                            <td><code><?= htmlspecialchars($m['target']) ?><?= $m['port'] ? ":{$m['port']}" : '' ?></code></td>
                            <td><?= $m['interval_seconds'] ?>s</td>
                            <td><small class="text-muted"><?= $m['last_check'] ? date('M d, H:i', strtotime($m['last_check'])) : 'Pending' ?></small></td>
                            <td><strong><?= number_format((float)$m['uptime_percentage'], 2) ?>%</strong></td>
                            <td class="text-end">
                                <form action="/admin/monitors/delete" method="POST" onsubmit="return confirm('Delete this monitor?');" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Monitor -->
<div class="modal fade" id="newMonitorModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/monitors/store" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Monitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Service / Route Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. MyETV Main API" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Probe Type</label>
                        <select name="type" id="probeType" class="form-select" onchange="togglePortField()">
                            <option value="http">HTTP / HTTPS</option>
                            <option value="ping">Ping (ICMP)</option>
                            <option value="port">TCP Port</option>
                            <option value="ssl">SSL Expiration Check</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Check Interval</label>
                        <select name="interval_seconds" class="form-select">
                            <option value="30">Every 30 seconds</option>
                            <option value="60" selected>Every 1 minute</option>
                            <option value="300">Every 5 minutes</option>
                            <option value="600">Every 10 minutes</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Target (URL / Host / IP)</label>
                    <input type="text" name="target" class="form-control" placeholder="https://api.myetv.tv or 1.1.1.1" required>
                </div>
                <div class="mb-3 d-none" id="portFieldWrapper">
                    <label class="form-label">Port Number</label>
                    <input type="number" name="port" class="form-control" placeholder="e.g. 3306, 22, 443">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Monitor</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePortField() {
    const type = document.getElementById('probeType').value;
    const portField = document.getElementById('portFieldWrapper');
    if (type === 'port') {
        portField.classList.remove('d-none');
    } else {
        portField.classList.add('d-none');
    }
}
</script>