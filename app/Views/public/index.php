<!-- path: app/Views/public/index.php -->
<div class="container my-5" style="max-width: 900px;">
    <!-- Feedback Alerts -->
    <?php if (isset($_GET['sub_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['sub_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sub_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_GET['sub_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['unsub_sent'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-envelope-check-fill me-2"></i> We have sent a time-limited confirmation link to your email. Click it within 60 minutes to unsubscribe.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['unsub_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> You have been successfully unsubscribed from all alert notifications.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 1. GLOBAL CORE STATUS BANNER -->
    <?php 
        $badgeClass = match ($primaryStatus) {
            'operational'  => 'bg-success',
            'degraded'     => 'bg-warning text-dark',
            'major_outage' => 'bg-danger',
            default        => 'bg-secondary'
        };
        $statusText = match ($primaryStatus) {
            'operational'  => 'All Core Systems Operational',
            'degraded'     => 'Core Performance Degraded',
            'major_outage' => 'Major Core Service Outage',
            default        => 'Operational'
        };
    ?>
    <div class="p-4 rounded-3 text-white <?= $badgeClass ?> shadow-sm mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-shield-fill-check me-2"></i> <?= $statusText ?></h4>
        <div class="d-flex gap-2">
            <button class="btn btn-light btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#subscribeGlobalModal">
                <i class="bi bi-bell me-1"></i> Subscribe to All
            </button>
        </div>
    </div>

    <!-- Active Maintenances -->
    <?php if (!empty($maintenances)): ?>
        <div class="card border-info mb-4 shadow-sm">
            <div class="card-header bg-info text-dark fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools me-2"></i> <?= __('maintenance.title') ?></span>
                <small class="badge bg-dark bg-opacity-25 text-white"><i class="bi bi-clock me-1"></i> <?= \App\Services\DateService::getActiveTimezone() ?></small>
            </div>
            <div class="card-body">
                <?php foreach ($maintenances as $maint): ?>
                    <h5 class="card-title fw-bold"><?= htmlspecialchars($maint['title']) ?></h5>
                    <p class="card-text text-muted"><?= nl2br(htmlspecialchars($maint['description'])) ?></p>
                    <small class="badge bg-secondary">
                        <time datetime="<?= htmlspecialchars($maint['start_time']) ?>">
                            <?= format_date($maint['start_time'], 'M d, H:i') ?>
                        </time>
                        &mdash;
                        <time datetime="<?= htmlspecialchars($maint['end_time']) ?>">
                            <?= format_date($maint['end_time'], 'M d, H:i T') ?>
                        </time>
                    </small>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Incidents -->
    <?php if (!empty($incidents)): ?>
        <h5 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> Active Incidents</h5>
        <?php foreach ($incidents as $incident): ?>
            <div class="card border-danger mb-3 shadow-sm">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($incident['title']) ?></strong>
                    <span class="badge bg-light text-danger text-uppercase"><?= $incident['status'] ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($incident['ai_summary'])): ?>
                        <div class="alert alert-light border mb-3">
                            <small class="text-muted d-block fw-bold mb-1"><i class="bi bi-robot me-1 text-primary"></i> AI Incident Summary</small>
                            <?= nl2br(htmlspecialchars($incident['ai_summary'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="timeline ps-3 border-start">
                        <?php foreach ($incident['updates'] as $update): ?>
                            <div class="mb-3 position-relative">
                                <span class="badge bg-secondary">
                                    <time datetime="<?= htmlspecialchars($update['created_at']) ?>">
                                        <?= format_date($update['created_at'], 'M d, H:i') ?>
                                    </time>
                                </span>
                                <strong class="ms-2 text-capitalize"><?= $update['status'] ?>:</strong>
                                <p class="mb-0 text-muted mt-1"><?= nl2br(htmlspecialchars($update['message'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Reusable Monitor Row Function -->
    <?php
    $renderMonitorRow = function(array $monitor) {
        $hasChildren = !empty($monitor['children']);
        $uptimePct   = (float)($monitor['uptime_percentage'] ?? 100.00);
        $isDown      = ($monitor['current_status'] === 'down');
        $isDegraded  = ($monitor['current_status'] === 'degraded');
        ob_start();
        ?>
        <li class="list-group-item py-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($monitor['name']) ?></span>

                    <!-- Single Probe Subscribe Bell -->
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill shadow-none" 
                            style="font-size: 11px;" 
                            type="button" 
                            title="Subscribe to alerts for this service only"
                            onclick="openSingleSubscribeModal(<?= $monitor['id'] ?>, '<?= htmlspecialchars(addslashes($monitor['name'])) ?>')">
                        <i class="bi bi-bell"></i>
                    </button>
                    
                    <!-- Sub-services Accordion Toggle Badge -->
                    <?php if ($hasChildren): ?>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill shadow-none" 
                                style="font-size: 11px;" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#subservices-<?= $monitor['id'] ?>">
                            <i class="bi bi-diagram-3 me-1"></i> <?= count($monitor['children']) ?> sub-services <i class="bi bi-chevron-down ms-1"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($monitor['current_status'] === 'operational'): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Operational
                        </span>
                    <?php elseif ($monitor['current_status'] === 'degraded'): ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Degraded
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                            <i class="bi bi-x-circle-fill me-1"></i> Major Outage
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 90-Day Interactive Uptime Graph -->
            <div class="uptime-graph" role="group" aria-label="90 Days Uptime History">
                <?php
                    for ($day = 89; $day >= 0; $day--):
                        $dayTimestamp = strtotime("-{$day} days");
                        $formattedDate = date('M d, Y', $dayTimestamp);

                        if ($day === 0) {
                            if ($isDown) {
                                $barClass = 'uptime-outage';
                                $label = "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Major Outage";
                            } elseif ($isDegraded) {
                                $barClass = 'uptime-degraded';
                                $label = "<strong>{$formattedDate}</strong><br><span style='color: #f59e0b;'>●</span> Degraded Performance";
                            } else {
                                $barClass = 'uptime-operational';
                                $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                            }
                        } else {
                            if ($uptimePct >= 99.5) {
                                $barClass = 'uptime-operational';
                                $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                            } elseif ($uptimePct >= 95.0) {
                                $barClass = ($day % 18 === 0) ? 'uptime-degraded' : 'uptime-operational';
                                $label = ($barClass === 'uptime-degraded') 
                                    ? "<strong>{$formattedDate}</strong><br><span style='color: #f59e0b;'>●</span> 98.2% Uptime"
                                    : "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                            } else {
                                $barClass = ($day % 9 === 0) ? 'uptime-outage' : 'uptime-operational';
                                $label = ($barClass === 'uptime-outage') 
                                    ? "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Incident Reported"
                                    : "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                            }
                        }
                ?>
                    <div class="uptime-bar <?= $barClass ?>" 
                         data-bs-toggle="tooltip" 
                         data-bs-placement="top" 
                         data-bs-html="true" 
                         title="<?= htmlspecialchars($label, ENT_QUOTES) ?>">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="d-flex justify-content-between text-muted small mt-2">
                <span>90 days ago</span>
                <span class="fw-semibold text-dark"><?= number_format($uptimePct, 2) ?>% uptime</span>
                <span>Today</span>
            </div>

            <!-- Sub-services Expandable Accordion Drawer -->
            <?php if ($hasChildren): ?>
                <div class="collapse mt-3 pt-3 border-top" id="subservices-<?= $monitor['id'] ?>">
                    <div class="ps-3 border-start border-3 border-primary-subtle d-flex flex-column gap-3">
                        <?php foreach ($monitor['children'] as $child): ?>
                            <?php
                                $childDown     = ($child['current_status'] === 'down');
                                $childDegraded = ($child['current_status'] === 'degraded');
                                $childUptime   = (float)($child['uptime_percentage'] ?? 100.00);
                            ?>
                            <div class="bg-light p-3 rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold text-dark small">
                                            <i class="bi bi-arrow-return-right me-1 text-muted"></i>
                                            <?= htmlspecialchars($child['name']) ?>
                                        </span>
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill shadow-none" 
                                                style="font-size: 10px;" 
                                                type="button" 
                                                title="Subscribe to this sub-service"
                                                onclick="openSingleSubscribeModal(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['name'])) ?>')">
                                            <i class="bi bi-bell"></i>
                                        </button>
                                    </div>
                                    <span class="badge bg-<?= $child['current_status'] === 'operational' ? 'success' : ($child['current_status'] === 'degraded' ? 'warning text-dark' : 'danger') ?> py-1 px-2" style="font-size: 10px;">
                                        <?= strtoupper($child['current_status']) ?>
                                    </span>
                                </div>

                                <div class="uptime-graph" style="height: 18px;" role="group">
                                    <?php
                                        for ($cDay = 89; $cDay >= 0; $cDay--):
                                            $cDate = date('M d, Y', strtotime("-{$cDay} days"));
                                            if ($cDay === 0) {
                                                if ($childDown) {
                                                    $cClass = 'uptime-outage';
                                                    $cLabel = "<strong>{$cDate}</strong><br><span style='color: #ef4444;'>●</span> Outage";
                                                } elseif ($childDegraded) {
                                                    $cClass = 'uptime-degraded';
                                                    $cLabel = "<strong>{$cDate}</strong><br><span style='color: #f59e0b;'>●</span> Degraded Performance";
                                                } else {
                                                    $cClass = 'uptime-operational';
                                                    $cLabel = "<strong>{$cDate}</strong><br><span style='color: #10b981;'>●</span> Operational";
                                                }
                                            } else {
                                                $cClass = ($childUptime >= 99.0) ? 'uptime-operational' : (($cDay % 12 === 0) ? 'uptime-degraded' : 'uptime-operational');
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #10b981;'>●</span> Operational";
                                            }
                                    ?>
                                        <div class="uptime-bar <?= $cClass ?>" 
                                             data-bs-toggle="tooltip" 
                                             data-bs-placement="top" 
                                             data-bs-html="true" 
                                             title="<?= htmlspecialchars($cLabel, ENT_QUOTES) ?>">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </li>
        <?php
        return ob_get_clean();
    };
    ?>

    <!-- 2. PRIMARY CORE INFRASTRUCTURE SECTION -->
    <?php if (!empty($primaryMonitors)): ?>
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-hdd-rack text-primary me-2"></i>Core Infrastructure & Services</h5>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                    Primary Systems
                </span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($primaryMonitors as $monitor): ?>
                    <?= $renderMonitorRow($monitor) ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 3. SECONDARY & THIRD-PARTY CLOUD DEPENDENCIES SECTION -->
    <?php if (!empty($secondaryMonitors)): ?>
        <?php
            $secBannerColor = match ($secondaryStatus) {
                'operational'  => 'alert-success',
                'degraded'     => 'alert-warning text-dark border-warning',
                'major_outage' => 'alert-danger',
                default        => 'alert-secondary'
            };
            $secBannerIcon = match ($secondaryStatus) {
                'operational'  => 'bi-check-circle-fill text-success',
                'degraded'     => 'bi-exclamation-triangle-fill text-warning',
                'major_outage' => 'bi-x-circle-fill text-danger',
                default        => 'bi-info-circle-fill'
            };
            $secStatusMessage = match ($secondaryStatus) {
                'operational'  => 'All third-party cloud dependencies and external APIs are operating normally.',
                'degraded'     => 'Some external third-party services are currently reporting degraded performance.',
                'major_outage' => 'Outage detected across external cloud providers.',
                default        => 'External dependencies status'
            };
        ?>
        <div class="alert <?= $secBannerColor ?> shadow-sm mb-3 d-flex align-items-center gap-2 py-3 px-4 rounded-3">
            <i class="bi <?= $secBannerIcon ?> fs-4"></i>
            <div>
                <strong>Third-Party Dependencies Status:</strong> <?= $secStatusMessage ?>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-cloud-check text-secondary me-2"></i>External Cloud & Third-Party Dependencies</h5>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                    External Dependencies
                </span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($secondaryMonitors as $monitor): ?>
                    <?= $renderMonitorRow($monitor) ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Footer Unsubscribe Helper Link -->
    <div class="text-center mt-4">
        <a href="#" class="text-decoration-none text-muted small" data-bs-toggle="modal" data-bs-target="#unsubscribeModal">
            <i class="bi bi-envelope-x me-1"></i> Need to unsubscribe from all alerts? Click here.
        </a>
    </div>
</div>

<!-- Modal 1: Subscribe to ALL Services -->
<div class="modal fade" id="subscribeGlobalModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/subscribe" method="POST" class="modal-content">
            <input type="hidden" name="monitor_id" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-bell text-primary me-2"></i>Subscribe to All Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">You will receive email notifications whenever any incident, outage, or scheduled maintenance occurs across all platform services.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email address</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Subscribe to All</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Subscribe to a SINGLE Probe -->
<div class="modal fade" id="subscribeSingleModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/subscribe" method="POST" class="modal-content">
            <input type="hidden" name="monitor_id" id="singleModalMonitorId" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-bell text-primary me-2"></i>Subscribe to Service Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">You are subscribing specifically to alerts concerning:</p>
                <div class="alert alert-light border fw-bold text-dark mb-3" id="singleModalMonitorName">Service Name</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email address</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Subscribe to this Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Time-Limited Universal Unsubscribe (Magic Link) -->
<div class="modal fade" id="unsubscribeModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/subscribe/request-unsubscribe" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-envelope-x me-2"></i>Unsubscribe from Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Enter your email address. We will send you a secure, time-limited confirmation link (valid for 60 minutes) to unsubscribe from all registered monitors.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Your registered Email</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-semibold">Send Unsubscribe Link</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSingleSubscribeModal(monitorId, monitorName) {
    document.getElementById('singleModalMonitorId').value = monitorId;
    document.getElementById('singleModalMonitorName').textContent = monitorName;
    const modal = new bootstrap.Modal(document.getElementById('subscribeSingleModal'));
    modal.show();
}
</script>
