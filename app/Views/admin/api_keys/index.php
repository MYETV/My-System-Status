<!-- path: app/Views/admin/api_keys/index.php -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">REST API Keys</h2>
            <p class="text-muted">Manage authentication tokens for automated scripts (CI/CD, Cloudflare Workers, Node.js).</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newKeyModal">
            <i class="bi bi-plus-lg me-1"></i> Generate New API Key
        </button>
    </div>

    <!-- One-Time Display of Newly Generated Plain Key -->
    <?php if (!empty($newKey)): ?>
        <div class="alert alert-success border-success shadow-sm mb-4">
            <h5 class="fw-bold"><i class="bi bi-check-circle-fill me-2"></i> New API Key Generated</h5>
            <p class="small mb-2">
                Make sure to copy your API key now. You won't be able to see it again!
            </p>
            <div class="input-group" style="max-width: 650px;">
                <input type="text" id="plainKeyInput" value="<?= htmlspecialchars($newKey) ?>" class="form-control font-monospace fw-bold bg-white" readonly>
                <button class="btn btn-dark" type="button" onclick="copyApiKey()">
                    <i class="bi bi-clipboard me-1"></i> Copy Key
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['revoked'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-trash-fill me-2"></i> API Key revoked successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- API Keys Table -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Key Description</th>
                        <th>Prefix</th>
                        <th>Created At</th>
                        <th>Last Used</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No API keys generated yet. Click above to create one.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($keys as $k): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($k['name']) ?></td>
                            <td><code class="text-muted"><?= htmlspecialchars($k['key_prefix']) ?></code></td>
                            <td><small class="text-muted"><?= format_date($k['created_at'], 'M d, Y') ?></small></td>
                            <td>
                                <small class="text-muted">
                                    <?= $k['last_used_at'] ? format_date($k['last_used_at'], 'M d, H:i') : 'Never' ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <form action="/admin/api-keys/delete" method="POST" class="d-inline" onsubmit="return confirm('Revoke this API key? Any scripts using it will be denied immediately.');">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke Key">
                                        <i class="bi bi-x-circle me-1"></i> Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Documentation Card -->
    <div class="card shadow-sm border-0 p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-code-slash text-primary me-2"></i>How to Use in Scripts (Bash & Node.js)</h5>
        <p class="text-muted small">Pass the API key in the <code>Authorization: Bearer &lt;KEY&gt;</code> header:</p>
        
        <div class="p-3 bg-dark text-white rounded font-monospace small mb-3">
            <span class="text-muted"># 1. Enable Maintenance Mode:</span><br>
            curl -X POST <?= htmlspecialchars(app_url()) ?>/api/v1/maintenance/enable \<br>
            &nbsp;&nbsp;-H "Authorization: Bearer YOUR_API_KEY" \<br>
            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
            &nbsp;&nbsp;-d '{"title": "Server Upgrades", "description": "Maintenance mode enabled via CI/CD"}'<br><br>
            <span class="text-muted"># 2. Disable Maintenance Mode (Close all active):</span><br>
            curl -X POST <?= htmlspecialchars(app_url()) ?>/api/v1/maintenance/disable \<br>
            &nbsp;&nbsp;-H "Authorization: Bearer YOUR_API_KEY"
        </div>
    </div>
</div>

<!-- Modal: Generate New API Key -->
<div class="modal fade" id="newKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/admin/api-keys/store" method="POST" class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Generate New API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Key Purpose / Label</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Node.js Cloudflare Deployment Script" required autofocus>
                    <small class="text-muted">Used to remember where this token is being used.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold">Generate API Key</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyApiKey() {
    const input = document.getElementById('plainKeyInput');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert('API Key copied to clipboard!');
}
</script>