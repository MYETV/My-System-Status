<!-- path: app/Views/admin/monitors/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Monitors & Services</h2>
            <p class="text-muted">Manage endpoints, customize display order, and configure probes.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" form="orderForm" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-down-up me-1"></i> Save Custom Order
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMonitorModal">
                <i class="bi bi-plus-lg me-1"></i> Add New Route
            </button>
        </div>
    </div>

    <!-- Reorder Success Alert -->
    <?php if (isset($_GET['reordered'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Monitor display order updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form id="orderForm" action="/admin/monitors/save-order" method="POST" class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;" class="text-center">Order</th>
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
                            <td colspan="9" class="text-center py-4 text-muted">No monitors found. Add your first route!</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($monitors as $m): ?>
                        <tr>
                            <!-- Custom Sort Order Input -->
                            <td class="text-center">
                                <input type="number" 
                                       name="order[<?= $m['id'] ?>]" 
                                       value="<?= (int)($m['sort_order'] ?? 0) ?>" 
                                       class="form-control form-control-sm text-center fw-bold mx-auto" 
                                       style="width: 65px;" 
                                       min="0" 
                                       max="999">
                            </td>
                            <td>
                                <?php if ($m['current_status'] === 'operational'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> UP</span>
                                <?php elseif ($m['current_status'] === 'down'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> DOWN</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> <?= strtoupper($m['current_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold">
                                <?php if (!empty($m['parent_id'])): ?>
                                    <span class="text-muted ms-2 me-1">↳</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($m['name']) ?>
                            </td>
                            <td><span class="badge bg-secondary text-uppercase"><?= $m['type'] ?></span></td>
                            <td><code class="small"><?= htmlspecialchars($m['target']) ?><?= $m['port'] ? ":{$m['port']}" : '' ?></code></td>
                            <td><?= $m['interval_seconds'] ?>s</td>
                            <td>
                                <small class="text-muted">
                                    <?= $m['last_check'] ? format_date($m['last_check'], 'M d, H:i') : 'Pending' ?>
                                </small>
                            </td>
                            <td><strong><?= number_format((float)$m['uptime_percentage'], 2) ?>%</strong></td>
                            <td class="text-end">
                                <button type="submit" form="deleteForm<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Monitor">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Standalone Delete Forms -->
<?php foreach ($monitors as $m): ?>
    <form id="deleteForm<?= $m['id'] ?>" action="/admin/monitors/delete" method="POST" onsubmit="return confirm('Delete this monitor?');">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
    </form>
<?php endforeach; ?>

<!-- Modal: Add Monitor -->
<div class="modal fade" id="newMonitorModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/monitors/store" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Monitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Service / Route Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. MyETV Main API" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Probe Type</label>
                        <select name="type" id="probeType" class="form-select" onchange="togglePortField()">
                            <option value="http">HTTP / HTTPS</option>
                            <option value="ping">Ping (ICMP)</option>
                            <option value="port">TCP Port</option>
                            <option value="ssl">SSL Expiration Check</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Check Interval</label>
                        <select name="interval_seconds" class="form-select">
                            <option value="30">Every 30 seconds</option>
                            <option value="60" selected>Every 1 minute</option>
                            <option value="300">Every 5 minutes</option>
                            <option value="600">Every 10 minutes</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Target (URL / Host / IP)</label>
                    <input type="text" name="target" class="form-control" placeholder="https://api.myetv.tv or 1.1.1.1" required>
                </div>
                <div class="mb-3 d-none" id="portFieldWrapper">
                    <label class="form-label fw-semibold">Port Number</label>
                    <input type="number" name="port" class="form-control" placeholder="e.g. 3306, 22, 443">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Create Monitor</button>
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
