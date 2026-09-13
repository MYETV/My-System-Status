<!-- path: app/Views/admin/maintenance/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Scheduled Maintenance</h2>
            <p class="text-muted">Schedule infrastructure upgrades, manage calendar events, and archive past works.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMaintenanceModal">
            <i class="bi bi-plus-lg me-1"></i> Schedule Maintenance
        </button>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Maintenance window successfully scheduled!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Maintenance details updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Maintenance window removed from database.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FullCalendar Interactive View -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-calendar-week text-primary me-2"></i>Interactive Calendar</h5>
            <small class="text-muted">Click any event on the calendar to edit or complete it.</small>
        </div>
        <div class="card-body p-4">
            <div id="calendar"></div>
        </div>
    </div>

    <!-- 1. UPCOMING & IN-PROGRESS MAINTENANCES -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="bi bi-clock-history me-2"></i>Active & Upcoming Maintenances
            </h5>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                <?= count($upcoming) ?> Active
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($upcoming)): ?>
                <div class="text-center py-4 text-muted small">No upcoming maintenances scheduled.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Title</th>
                                <th>Start Window</th>
                                <th>End Window</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $m): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $m['status'] === 'in_progress' ? 'warning text-dark' : 'primary' ?> text-uppercase">
                                            <?= str_replace('_', ' ', $m['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($m['title']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($m['description'] ?? '', 0, 80, '...')) ?></small>
                                    </td>
                                    <td><small class="text-muted"><?= format_date($m['start_time'], 'M d, Y H:i') ?></small></td>
                                    <td><small class="text-muted"><?= format_date($m['end_time'], 'M d, Y H:i') ?></small></td>
                                    <td class="text-end">
                                        <!-- Quick Mark as Completed Button -->
                                        <form action="/admin/maintenance/update" method="POST" class="d-inline">
                                            <input type="hidden" name="maintenance_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="title" value="<?= htmlspecialchars($m['title']) ?>">
                                            <input type="hidden" name="description" value="<?= htmlspecialchars($m['description'] ?? '') ?>">
                                            <input type="hidden" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($m['start_time'])) ?>">
                                            <input type="hidden" name="end_time" value="<?= date('Y-m-d\TH:i', strtotime($m['end_time'])) ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-success me-1" title="Mark as Completed">
                                                <i class="bi bi-check-lg me-1"></i> Complete
                                            </button>
                                        </form>

                                        <!-- Edit Modal Trigger -->
                                        <button class="btn btn-sm btn-outline-secondary me-1" 
                                                onclick="openEditMaintenanceModal(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['title'])) ?>', '<?= htmlspecialchars(addslashes($m['description'] ?? '')) ?>', '<?= date('Y-m-d\TH:i', strtotime($m['start_time'])) ?>', '<?= date('Y-m-d\TH:i', strtotime($m['end_time'])) ?>', '<?= $m['status'] ?>')"
                                                title="Edit Details">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Trigger -->
                                        <form action="/admin/maintenance/delete" method="POST" class="d-inline" onsubmit="return confirm('Delete this maintenance?');">
                                            <input type="hidden" name="maintenance_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. PAST & COMPLETED MAINTENANCES (ARCHIVE) -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary">
                <i class="bi bi-archive me-2"></i>Past & Completed Maintenances
            </h5>
            <span class="badge bg-light text-secondary border">
                <?= count($past) ?> Archived
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($past)): ?>
                <div class="text-center py-4 text-muted small">No past maintenances recorded.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Execution Window</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past as $p): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($p['title']) ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= format_date($p['start_time'], 'M d, H:i') ?> &mdash; <?= format_date($p['end_time'], 'M d, H:i') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            Completed
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form action="/admin/maintenance/delete" method="POST" class="d-inline" onsubmit="return confirm('Delete this archived record?');">
                                            <input type="hidden" name="maintenance_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Schedule New Maintenance -->
<div class="modal fade" id="newMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/maintenance/store" method="POST" class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Schedule Maintenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Database Cluster Optimization" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Describe the expected impact or downtime window..."></textarea>
                </div>
                <div class="mb-3">
    <label class="form-label fw-semibold">Target Service (Optional)</label>
    <select name="monitor_id" class="form-select">
        <option value="">All Services (Global Maintenance)</option>
        <?php foreach ($monitorsList as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted">Associates this maintenance window to the selected service bar.</small>
</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Start Window</label>
                        <input type="datetime-local" name="start_time" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">End Window</label>
                        <input type="datetime-local" name="end_time" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Initial Status</label>
                    <select name="status" class="form-select">
                        <option value="scheduled" selected>Scheduled</option>
                        <option value="in_progress">In Progress</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">Schedule Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Maintenance (Opened via button or calendar event click) -->
<div class="modal fade" id="editMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/maintenance/update" method="POST" class="modal-content shadow">
            <input type="hidden" name="maintenance_id" id="editMaintId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Maintenance Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title</label>
                    <input type="text" name="title" id="editMaintTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" id="editMaintDesc" class="form-control" rows="3"></textarea>
                </div>
<div class="mb-3">
    <label class="form-label fw-semibold">Target Service (Optional)</label>
    <select name="monitor_id" class="form-select">
        <option value="">All Services (Global Maintenance)</option>
        <?php foreach ($monitorsList as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted">Associates this maintenance window to the selected service bar.</small>
</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Start Window</label>
                        <input type="datetime-local" name="start_time" id="editMaintStart" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">End Window</label>
                        <input type="datetime-local" name="end_time" id="editMaintEnd" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" id="editMaintStatus" class="form-select">
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed" class="text-success fw-bold">Completed (Archive to past events)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: '/admin/maintenance/events',
        // Click on calendar event to open edit modal immediately!
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const event = info.event;
            const props = event.extendedProps;
            openEditMaintenanceModal(
                event.id,
                event.title,
                props.description || '',
                props.start_raw,
                props.end_raw,
                props.status
            );
        }
    });
    calendar.render();
});

function openEditMaintenanceModal(id, title, desc, start, end, status) {
    document.getElementById('editMaintId').value = id;
    document.getElementById('editMaintTitle').value = title;
    document.getElementById('editMaintDesc').value = desc;
    document.getElementById('editMaintStart').value = start;
    document.getElementById('editMaintEnd').value = end;
    document.getElementById('editMaintStatus').value = status;
    new bootstrap.Modal(document.getElementById('editMaintenanceModal')).show();
}
</script>
