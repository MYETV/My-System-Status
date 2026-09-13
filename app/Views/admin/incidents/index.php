<!-- path: app/Views/admin/incidents/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Incidents & Outages</h2>
            <p class="text-muted">Communicate downtimes, manage progress timelines, and resolve issues.</p>
        </div>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
            <i class="bi bi-exclamation-octagon me-1"></i> Declare Incident
        </button>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Incident declared and published!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Incident status update posted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['edited'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Incident details modified successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Incident removed from database.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 1. ACTIVE & ONGOING INCIDENTS SECTION -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Active & Ongoing Incidents
            </h5>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                <?= count($activeIncidents) ?> Active
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($activeIncidents)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shield-fill-check text-success fs-1 d-block mb-2"></i>
                    <h5 class="fw-bold text-dark">All Systems Operational</h5>
                    <p class="small mb-0">There are currently no active or unresolved incidents reported.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Impact</th>
                                <th>Incident Title</th>
                                <th>Current Status</th>
                                <th>Created At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeIncidents as $inc): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $inc['impact'] === 'critical' ? 'danger' : ($inc['impact'] === 'major' ? 'warning text-dark' : 'secondary') ?>">
                                            <?= strtoupper($inc['impact']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($inc['title']) ?></strong>
                                        <?php if (!empty($inc['ai_summary'])): ?>
                                            <small class="text-muted"><i class="bi bi-robot text-primary me-1"></i><?= htmlspecialchars(mb_strimwidth($inc['ai_summary'], 0, 75, '...')) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase px-2 py-1">
                                            <?= $inc['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= format_date($inc['created_at'], 'M d, H:i') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary me-1" 
                                                onclick="openUpdateModal(<?= $inc['id'] ?>, '<?= htmlspecialchars(addslashes($inc['title'])) ?>', '<?= $inc['status'] ?>')"
                                                title="Post Progress Note or Mark as Resolved">
                                            <i class="bi bi-check2-circle me-1"></i> Update / Resolve
                                        </button>

                                        <button class="btn btn-sm btn-outline-secondary me-1" 
                                                onclick="openEditModal(<?= $inc['id'] ?>, '<?= htmlspecialchars(addslashes($inc['title'])) ?>', '<?= $inc['impact'] ?>', '<?= htmlspecialchars(addslashes($inc['ai_summary'] ?? '')) ?>')"
                                                title="Edit Incident Details">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <form action="/admin/incidents/delete" method="POST" class="d-inline" onsubmit="return confirm('Delete this incident?');">
                                            <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
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

    <!-- 2. PAST & RESOLVED INCIDENTS SECTION -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary">
                <i class="bi bi-archive me-2"></i>Past & Resolved Incidents
            </h5>
            <span class="badge bg-light text-secondary border">
                <?= count($resolvedIncidents) ?> Archived
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($resolvedIncidents)): ?>
                <div class="text-center py-4 text-muted small">No resolved incidents in history.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Incident Title</th>
                                <th>Impact</th>
                                <th>Resolved Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resolvedIncidents as $inc): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($inc['title']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border text-uppercase">
                                            <?= $inc['impact'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= format_date($inc['updated_at'], 'M d, Y H:i') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <!-- Reopen button -->
                                        <button class="btn btn-sm btn-outline-warning me-1" 
                                                onclick="openUpdateModal(<?= $inc['id'] ?>, '<?= htmlspecialchars(addslashes($inc['title'])) ?>', 'monitoring')"
                                                title="Reopen Incident">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reopen
                                        </button>

                                        <form action="/admin/incidents/delete" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this archived incident?');">
                                            <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
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

<!-- Modal 1: Post Update / Mark as Resolved -->
<div class="modal fade" id="postUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/incidents/update-status" method="POST" class="modal-content shadow">
            <input type="hidden" name="incident_id" id="updateModalIncidentId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="updateModalTitle">Post Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Status</label>
                    <select name="status" id="updateModalStatusSelect" class="form-select" required>
                        <option value="investigating">Investigating (Initial detection)</option>
                        <option value="identified">Identified (Cause identified)</option>
                        <option value="monitoring">Monitoring (Fix implemented, monitoring metrics)</option>
                        <option value="resolved" class="fw-bold text-success">Resolved (Closed & archived to past incidents)</option>
                    </select>
                    <small class="text-muted">Setting status to <strong>Resolved</strong> will archive it under past incidents.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Timeline Note / Message</label>
                    <textarea name="message" class="form-control" rows="3" placeholder="e.g. The root cause was fixed and all services have recovered..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">Publish Timeline Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Incident Details -->
<div class="modal fade" id="editIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/admin/incidents/update" method="POST" class="modal-content shadow">
            <input type="hidden" name="incident_id" id="editModalIncidentId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Incident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Incident Title</label>
                    <input type="text" name="title" id="editModalTitleInput" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Impact Severity</label>
                    <select name="impact" id="editModalImpactSelect" class="form-select">
                        <option value="minor">Minor Performance Degradation</option>
                        <option value="major">Major Service Disruption</option>
                        <option value="critical">Critical Outage</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">AI Public Summary</label>
                    <textarea name="ai_summary" id="editModalAiSummaryInput" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Declare New Incident (with AI Draft) -->
<div class="modal fade" id="newIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/admin/incidents/store" method="POST" class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Declare New Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Incident Title</label>
                    <input type="text" name="title" id="incidentTitle" class="form-control" placeholder="e.g. Core API Cluster Latency Spikes" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Impact Level</label>
                        <select name="impact" class="form-select">
                            <option value="minor">Minor Performance Degradation</option>
                            <option value="major">Major Service Disruption</option>
                            <option value="critical">Critical Infrastructure Outage</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="investigating">Investigating</option>
                            <option value="identified">Identified</option>
                            <option value="monitoring">Monitoring</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Technical Error / Raw Logs (for AI analysis)</label>
                    <textarea id="rawErrorDetails" class="form-control" rows="2" placeholder="Paste probe failure log or stack trace here..."></textarea>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold mb-0">Public AI-Assisted Summary</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenerateAi" onclick="fetchAiSummary()">
                            <i class="bi bi-robot me-1"></i> Draft with AI
                        </button>
                    </div>
                    <textarea name="ai_summary" id="aiSummaryField" class="form-control" rows="2" placeholder="AI-generated public summary will appear here."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Initial Timeline Note</label>
                    <textarea name="message" class="form-control" rows="2" placeholder="Our engineering team is actively investigating the issue..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-bold">Publish Incident</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateModal(id, title, currentStatus) {
    document.getElementById('updateModalIncidentId').value = id;
    document.getElementById('updateModalTitle').textContent = 'Update: ' + title;
    document.getElementById('updateModalStatusSelect').value = currentStatus;
    new bootstrap.Modal(document.getElementById('postUpdateModal')).show();
}

function openEditModal(id, title, impact, aiSummary) {
    document.getElementById('editModalIncidentId').value = id;
    document.getElementById('editModalTitleInput').value = title;
    document.getElementById('editModalImpactSelect').value = impact;
    document.getElementById('editModalAiSummaryInput').value = aiSummary;
    new bootstrap.Modal(document.getElementById('editIncidentModal')).show();
}

async function fetchAiSummary() {
    const btn = document.getElementById('btnGenerateAi');
    const title = document.getElementById('incidentTitle').value || 'Service Outage';
    const errorDetails = document.getElementById('rawErrorDetails').value || 'Service timeout';
    const target = document.getElementById('aiSummaryField');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

    try {
        const res = await fetch('/admin/incidents/ai-generate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({service_name: title, error_details: errorDetails})
        });
        const data = await res.json();
        if (data.status === 'success') {
            target.value = data.summary;
        } else {
            alert('Failed to generate AI report.');
        }
    } catch (e) {
        console.error(e);
        alert('AI communication error.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-robot me-1"></i> Draft with AI';
    }
}
</script>
