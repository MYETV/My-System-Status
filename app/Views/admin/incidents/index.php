<!-- path: app/Views/admin/incidents/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Incidents & Outages</h2>
            <p class="text-muted">Communicate downtimes, active degraded statuses, and public explanations.</p>
        </div>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
            <i class="bi bi-exclamation-octagon me-1"></i> Declare Incident
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Impact</th>
                        <th>Status</th>
                        <th>AI Report</th>
                        <th>Created At (<?= htmlspecialchars(\App\Services\DateService::getActiveTimezone()) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incidents)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No incidents logged. Everything is operational!</td></tr>
                    <?php endif; ?>

                    <?php foreach ($incidents as $inc): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($inc['title']) ?></td>
                            <td>
                                <span class="badge bg-<?= $inc['impact'] === 'critical' ? 'danger' : ($inc['impact'] === 'major' ? 'warning text-dark' : 'secondary') ?>">
                                    <?= strtoupper($inc['impact']) ?>
                                </span>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper($inc['status']) ?></span></td>
                            <td style="max-width: 320px;">
                                <small class="text-muted"><?= htmlspecialchars($inc['ai_summary'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <time datetime="<?= htmlspecialchars($inc['created_at']) ?>">
                                        <?= format_date($inc['created_at'], 'M d, Y H:i') ?>
                                    </time>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: New Incident -->
<div class="modal fade" id="newIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/admin/incidents/store" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Declare New Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Incident Title</label>
                    <input type="text" name="title" id="incidentTitle" class="form-control" placeholder="e.g. Media Transcoding Cluster Degradation" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Impact Level</label>
                        <select name="impact" class="form-select">
                            <option value="minor">Minor Performance Degradation</option>
                            <option value="major">Major Service Disruption</option>
                            <option value="critical">Critical Infrastructure Outage</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="investigating">Investigating</option>
                            <option value="identified">Identified</option>
                            <option value="monitoring">Monitoring</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Technical Raw Error / Log Details</label>
                    <textarea id="rawErrorDetails" class="form-control" rows="2" placeholder="Paste probe failure log or stack trace here..."></textarea>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Public AI-Assisted Summary</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenerateAi" onclick="fetchAiSummary()">
                            <i class="bi bi-robot me-1"></i> Draft with AI
                        </button>
                    </div>
                    <textarea name="ai_summary" id="aiSummaryField" class="form-control" rows="3" placeholder="AI-generated public summary will appear here."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Initial Timeline Update Note</label>
                    <textarea name="message" class="form-control" rows="2" placeholder="Our engineering team is actively investigating the issue..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Publish Incident</button>
            </div>
        </form>
    </div>
</div>

<script>
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