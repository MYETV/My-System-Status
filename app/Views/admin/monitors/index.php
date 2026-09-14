<!-- path: app/Views/admin/monitors/index.php -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    /* Custom DataTables Styling Enhancements */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        border: 1px solid #dee2e6;
    }
    .dataTables_wrapper .dataTables_length select {
        border-radius: 0.375rem;
        padding: 0.375rem 2rem 0.375rem 0.75rem;
        border: 1px solid #dee2e6;
    }
    .dataTables_info, .dataTables_paginate {
        padding-top: 1rem !important;
        padding-bottom: 0.5rem !important;
    }
    tr.dt-child-row td {
        background-color: #f8f9fa !important;
        padding: 0 !important;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Monitors & Services</h2>
            <p class="text-muted mb-0">Manage core endpoints, secondary cloud feeds, and display order.</p>
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

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> New monitor route created successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Monitor configuration updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Monitor deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form id="orderForm" action="/admin/monitors/save-order" method="POST" class="card shadow-sm border-0 p-3">
        <div class="table-responsive">
            <table id="monitorsTable" class="table table-hover align-middle mb-0 w-100">
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
                    <?php foreach ($monitors as $m): ?>
                        <?php 
                            $hasChildren = !empty($m['children']); 
                            $isExternal  = !empty($m['is_external']);
                        ?>
                        <tr data-monitor-id="<?= $m['id'] ?>">
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
                                    <?php if ($isExternal): ?>
                                        <span class="badge bg-light text-dark border small" title="Automated plugin or Zero Trust tunnel feed">
                                            <i class="bi bi-plugin me-1 text-primary"></i> Feed / Tunnel
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasChildren): ?>
                                        <button class="btn btn-xs btn-outline-primary py-0 px-2 rounded-pill btn-toggle-subservices" 
                                                style="font-size: 11px;" 
                                                type="button" 
                                                data-monitor-id="<?= $m['id'] ?>">
                                            <span><?= count($m['children']) ?> sub-services</span> <i class="bi bi-chevron-down ms-1"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="max-width: 220px;" class="text-truncate">
                                <code class="small text-muted"><?= htmlspecialchars($m['target']) ?></code>
                            </td>
                            <td><strong><?= number_format((float)($m['uptime_percentage'] ?? 100), 2) ?>%</strong></td>
                            <td>
                                <?php if ((int)($m['is_primary'] ?? 0) === 1): ?>
                                    <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i> Core Service</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Secondary</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <?php if ($isExternal): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Feed and Tunnel monitors cannot be edited manually.">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMonitorModal<?= $m['id'] ?>" title="Edit Monitor">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" form="deleteForm<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Monitor and all sub-services">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<?php foreach ($monitors as $m): ?>
    <?php if (!empty($m['children'])): ?>
        <template id="child-template-<?= $m['id'] ?>">
            <div class="p-3 bg-light border-top border-bottom">
                <div class="ps-4 border-start border-3 border-primary">
                    <h6 class="fw-bold text-muted small mb-2">Sub-services under <?= htmlspecialchars($m['name']) ?>:</h6>
                    <div class="row g-2">
                        <?php foreach ($m['children'] as $child): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded border small">
                                    <span class="text-truncate fw-semibold"><?= htmlspecialchars($child['name']) ?></span>
                                    <span class="badge bg-<?= $child['current_status'] === 'operational' ? 'success' : 'warning text-dark' ?> ms-2">
                                        <?= strtoupper($child['current_status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </template>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Standalone Delete Forms -->
<?php foreach ($monitors as $m): ?>
    <form id="deleteForm<?= $m['id'] ?>" action="/admin/monitors/delete" method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($m['name'])) ?> and all of its sub-services?');">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
    </form>
<?php endforeach; ?>

<?php foreach ($monitors as $m): ?>
    <?php if (empty($m['is_external'])): ?>
        <div class="modal fade" id="editMonitorModal<?= $m['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form action="/admin/monitors/update" method="POST" class="modal-content">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Edit Monitor: <?= htmlspecialchars($m['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Service / Route Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($m['name']) ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Probe Type</label>
                                <select name="type" id="probeTypeEdit<?= $m['id'] ?>" class="form-select" onchange="togglePortFieldEdit(<?= $m['id'] ?>)">
                                    <option value="http" <?= $m['type'] === 'http' ? 'selected' : '' ?>>HTTP / HTTPS</option>
                                    <option value="ping" <?= $m['type'] === 'ping' ? 'selected' : '' ?>>Ping (ICMP)</option>
                                    <option value="port" <?= $m['type'] === 'port' ? 'selected' : '' ?>>TCP Port</option>
                                    <option value="ssl" <?= $m['type'] === 'ssl' ? 'selected' : '' ?>>SSL Expiration Check</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Check Interval</label>
                                <select name="interval_seconds" class="form-select">
                                    <option value="30" <?= (int)$m['interval_seconds'] === 30 ? 'selected' : '' ?>>Every 30 seconds</option>
                                    <option value="60" <?= (int)$m['interval_seconds'] === 60 ? 'selected' : '' ?>>Every 1 minute</option>
                                    <option value="300" <?= (int)$m['interval_seconds'] === 300 ? 'selected' : '' ?>>Every 5 minutes</option>
                                    <option value="600" <?= (int)$m['interval_seconds'] === 600 ? 'selected' : '' ?>>Every 10 minutes</option>
                                    <option value="900" <?= (int)$m['interval_seconds'] === 900 ? 'selected' : '' ?>>Every 15 minutes</option>
                                    <option value="1800" <?= (int)$m['interval_seconds'] === 1800 ? 'selected' : '' ?>>Every 30 minutes</option>
                                    <option value="3600" <?= (int)$m['interval_seconds'] === 3600 ? 'selected' : '' ?>>Every 1 hour</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target (URL / Host / IP)</label>
                            <input type="text" name="target" class="form-control" value="<?= htmlspecialchars($m['target']) ?>" required>
                        </div>

                        <div class="mb-3 <?= $m['type'] === 'port' ? '' : 'd-none' ?>" id="portFieldWrapperEdit<?= $m['id'] ?>">
                            <label class="form-label fw-semibold">Port Number</label>
                            <input type="number" name="port" class="form-control" value="<?= htmlspecialchars($m['port'] ?? '') ?>" placeholder="e.g. 3306, 22, 443">
                        </div>

                        <!-- Parent Group Assignment -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Parent Service Group (Optional)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">None (Standalone / Parent Service)</option>
                                <?php foreach ($parentsList as $parent): ?>
                                    <?php if ($parent['id'] != $m['id']): ?>
                                        <option value="<?= $parent['id'] ?>" <?= $m['parent_id'] == $parent['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($parent['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Primary Core Service Checkbox -->
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="primarySwitchEdit<?= $m['id'] ?>" <?= (int)$m['is_primary'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="primarySwitchEdit<?= $m['id'] ?>">
                                Mark as Core / Primary Service
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

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
                            <option value="600">Every 10 minutes</option>
                            <option value="900">Every 15 minutes</option>
                            <option value="1800">Every 30 minutes</option>
                            <option value="3600">Every 1 hour</option>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables cleanly without DOM column count mismatches
    if ($('#monitorsTable tbody tr').length > 0) {
        var table = $('#monitorsTable').DataTable({
            ordering: false, // Maintain custom display order
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search monitors, routes, types...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ monitors",
                infoEmpty: "No monitors available",
                zeroRecords: "No matching monitors found"
            },
            columnDefs: [
                { orderable: false, targets: '_all' }
            ]
        });

        // Toggle Sub-services Drawer via DataTables Native Child Rows API
        $('#monitorsTable tbody').on('click', '.btn-toggle-subservices', function () {
            var tr = $(this).closest('tr');
            var row = table.row(tr);
            var mId = $(this).data('monitor-id');
            var template = document.getElementById('child-template-' + mId);

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('dt-has-child-expanded');
                $(this).find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            } else if (template) {
                row.child(template.innerHTML, 'dt-child-row').show();
                tr.addClass('dt-has-child-expanded');
                $(this).find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            }
        });
    }
});

function togglePortField() {
    const type = document.getElementById('probeType').value;
    const portField = document.getElementById('portFieldWrapper');
    if (type === 'port') {
        portField.classList.remove('d-none');
    } else {
        portField.classList.add('d-none');
    }
}

function togglePortFieldEdit(id) {
    const type = document.getElementById('probeTypeEdit' + id).value;
    const portField = document.getElementById('portFieldWrapperEdit' + id);
    if (type === 'port') {
        portField.classList.remove('d-none');
    } else {
        portField.classList.add('d-none');
    }
}
</script>
