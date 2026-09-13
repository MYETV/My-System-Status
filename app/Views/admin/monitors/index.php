<!-- path: app/Views/admin/monitors/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Monitors & Services</h2>
            <p class="text-muted">Manage core endpoints, secondary cloud feeds, and display order.</p>
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
                        <th style="width: 70px;" class="text-center">Order</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Service Name</th>
                        <th>Target</th>
                        <th>Uptime</th>
                        <th>Tier</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitors)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No monitors found.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($monitors as $m): ?>
                        <?php $hasChildren = !empty($m['children']); ?>
                        <tr>
                            <!-- Order Input (Applies only to root monitor) -->
                            <td class="text-center">
                                <input type="number" 
                                       name="order[<?= $m['id'] ?>]" 
                                       value="<?= (int)($m['sort_order'] ?? 0) ?>" 
                                       class="form-control form-control-sm text-center fw-bold mx-auto" 
                                       style="width: 60px;">
                            </td>
                            <td>
                                <?php if ($m['current_status'] === 'operational'): ?>
                                    <span class="badge bg-success">UP</span>
                                <?php elseif ($m['current_status'] === 'down'): ?>
                                    <span class="badge bg-danger">DOWN</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= strtoupper($m['current_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary text-uppercase"><?= $m['type'] ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-dark"><?= htmlspecialchars($m['name']) ?></strong>
                                    <?php if ($hasChildren): ?>
                                        <button class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill" 
                                                style="font-size: 11px;" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#adminChildren<?= $m['id'] ?>">
                                            <?= count($m['children']) ?> sub-services <i class="bi bi-chevron-down"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="max-width: 220px;" class="text-truncate">
                                <code class="small text-muted"><?= htmlspecialchars($m['target']) ?></code>
                            </td>
                            <td><strong><?= number_format((float)$m['uptime_percentage'], 2) ?>%</strong></td>
                            <td>
                                <?php if ((int)($m['is_primary'] ?? 0) === 1): ?>
                                    <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i> Core Service</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Secondary</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="submit" form="deleteForm<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Monitor and all sub-services">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Sub-services Drawer in Admin Table -->
                        <?php if ($hasChildren): ?>
                            <tr class="collapse bg-light" id="adminChildren<?= $m['id'] ?>">
                                <td colspan="8" class="p-3">
                                    <div class="ps-4 border-start border-3 border-primary">
                                        <h6 class="fw-bold text-muted small mb-2">Sub-services under <?= htmlspecialchars($m['name']) ?>:</h6>
                                        <div class="row g-2">
                                            <?php foreach ($m['children'] as $child): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded border small">
                                                        <span class="text-truncate"><?= htmlspecialchars($child['name']) ?></span>
                                                        <span class="badge bg-<?= $child['current_status'] === 'operational' ? 'success' : 'warning text-dark' ?> ms-2">
                                                            <?= strtoupper($child['current_status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Standalone Delete Forms -->
<?php foreach ($monitors as $m): ?>
    <form id="deleteForm<?= $m['id'] ?>" action="/admin/monitors/delete" method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($m['name'])) ?> and all of its sub-services?');">
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
                    <input type="text" name="name" class="form-control" placeholder="e.g. MYETV Core API" required>
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
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Target (URL / Host / IP)</label>
                    <input type="text" name="target" class="form-control" placeholder="https://api.myetv.tv" required>
                </div>

                <div class="mb-3 d-none" id="portFieldWrapper">
                    <label class="form-label fw-semibold">Port Number</label>
                    <input type="number" name="port" class="form-control" placeholder="e.g. 3306, 22, 443">
                </div>

                <!-- Parent Group Assignment (Optional) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Parent Service Group (Optional)</label>
                    <select name="parent_id" class="form-select">
                        <option value="">None (Standalone / Parent Service)</option>
                        <?php foreach ($parentsList as $parent): ?>
                            <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Group this monitor under an existing service.</small>
                </div>

                <!-- Primary Core Service Checkbox -->
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="primarySwitch" checked>
                    <label class="form-check-label fw-semibold" for="primarySwitch">
                        Mark as Core / Primary Service
                    </label>
                    <div class="text-muted small">If a Core Service goes down, the global status banner turns into Major Outage.</div>
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
