<!-- path: app/Views/admin/logs/index.php -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    /* DataTables Visual Polish */
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
</style>

<div class="container-fluid py-4">
    <!-- Header with 24-Hour Date Picker -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-0">System Audit & Probe Logs</h2>
            <p class="text-muted mb-0">Inspect 24-hour heartbeat telemetry windows and diagnostic errors.</p>
        </div>
        <div>
            <!-- Date Filter (24-Hour Window) -->
            <form action="/admin/logs" method="GET" class="d-flex align-items-center gap-2">
                <label for="log_date" class="form-label small fw-semibold mb-0 text-nowrap">
                    <i class="bi bi-calendar3 me-1"></i> 24h Window:
                </label>
                <input type="date" 
                       id="log_date" 
                       name="date" 
                       value="<?= htmlspecialchars($selectedDate ?? date('Y-m-d')) ?>" 
                       max="<?= date('Y-m-d') ?>"
                       class="form-control form-control-sm shadow-sm" 
                       onchange="this.form.submit()">
                <?php if (!empty($selectedDate) && $selectedDate !== date('Y-m-d')): ?>
                    <a href="/admin/logs" class="btn btn-sm btn-primary text-nowrap">Today</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 24-Hour Metric KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 fs-4">
                        <i class="bi bi-activity"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Checks in 24h</div>
                        <h4 class="fw-bold mb-0"><?= number_format($totalChecks ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 fs-4">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Successful (UP)</div>
                        <h4 class="fw-bold mb-0 text-success"><?= number_format($upChecks ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-4">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Outages (DOWN)</div>
                        <h4 class="fw-bold mb-0 text-danger"><?= number_format($downChecks ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-dark bg-opacity-10 text-dark rounded-3 fs-4">
                        <i class="bi bi-speedometer"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Average Latency</div>
                        <h4 class="fw-bold mb-0"><?= (int)($avgLatency ?? 0) ?> ms</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DataTables Card -->
    <div class="card shadow-sm border-0 p-3">
        <div class="table-responsive">
            <table id="logsTable" class="table table-hover align-middle mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th style="width: 170px;">Date & Time (<?= htmlspecialchars(\App\Services\DateService::getActiveTimezone()) ?>)</th>
                        <th>Monitor Name</th>
                        <th>Status</th>
                        <th>Latency</th>
                        <th>HTTP Code</th>
                        <th>Diagnostic Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($probeLogs as $log): ?>
                        <tr>
                            <td>
                                <span class="font-monospace small text-muted">
                                    <?= format_date($log['created_at'], 'H:i:s') ?>
                                    <small class="text-secondary">(<?= format_date($log['created_at'], 'M d') ?>)</small>
                                </span>
                            </td>
                            <td class="fw-semibold text-dark">
                                <?= htmlspecialchars($log['monitor_name'] ?? 'Unknown Service') ?>
                            </td>
                            <td>
                                <?php if (($log['status'] ?? '') === 'up'): ?>
                                    <span class="badge bg-success">UP</span>
                                <?php elseif (($log['status'] ?? '') === 'blackout'): ?>
                                    <span class="badge bg-dark">BLACKOUT</span>
                                <?php elseif (($log['status'] ?? '') === 'timeout'): ?>
                                    <span class="badge bg-warning text-dark">TIMEOUT</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">DOWN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace">
                                    <?= (int)($log['response_time_ms'] ?? 0) ?> ms
                                </span>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($log['http_code'] ?? '-') ?></code>
                            </td>
                            <td>
                                <?php if (!empty($log['error_message'])): ?>
                                    <small class="text-danger fw-semibold"><?= htmlspecialchars($log['error_message']) ?></small>
                                <?php else: ?>
                                    <small class="text-success"><i class="bi bi-check2"></i> Operational</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#logsTable').DataTable({
        order: [[0, 'desc']], // Most recent check first
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 250, -1], [25, 50, 100, 250, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search within 24h logs...",
            lengthMenu: "Show _MENU_ logs",
            info: "Showing _START_ to _END_ of _TOTAL_ logs (24h Window)",
            infoEmpty: "No logs recorded for this 24h window",
            zeroRecords: "No matching logs found in this date window"
        }
    });
});
</script>
