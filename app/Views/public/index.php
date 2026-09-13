<!-- path: app/Views/public/index.php -->
<?php
$uptimeHistory   = $uptimeHistory ?? [];
$allIncidents    = $allIncidents ?? [];
$allMaintenances = $allMaintenances ?? [];
?>
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
            <i class="bi bi-envelope-check-fill me-2"></i> We have sent a secure confirmation link to your email. Click it within 60 minutes to finalize unsubscription.
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
        <button class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-1" 
                type="button" 
                onclick="openSubscriptionModal(null, 'All Core & Platform Services')">
            <i class="bi bi-bell-fill text-primary"></i> 
            <span>Subscribe / Unsubscribe</span>
        </button>
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

    <!-- Reusable Monitor Row Function with Day Click Details -->
    <?php
    $renderMonitorRow = function(array $monitor) use ($uptimeHistory, $allIncidents, $allMaintenances) {
        $hasChildren = !empty($monitor['children']);
        $uptimePct   = (float)($monitor['uptime_percentage'] ?? 100.00);
        $isDown      = ($monitor['current_status'] === 'down');
        $isDegraded  = ($monitor['current_status'] === 'degraded');
        $mId         = (int)$monitor['id'];
        ob_start();
        ?>
        <li class="list-group-item py-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($monitor['name']) ?></span>

                    <!-- Single Probe Subscribe Button -->
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill shadow-none d-flex align-items-center gap-1" 
                            style="font-size: 11px;" 
                            type="button" 
                            onclick="openSubscriptionModal(<?= $monitor['id'] ?>, '<?= htmlspecialchars(addslashes($monitor['name'])) ?>')">
                        <i class="bi bi-bell"></i>
                        <span>Subscribe / Unsubscribe</span>
                    </button>
                    
                    <!-- Sub-services Accordion Toggle Badge -->
                    <?php if ($hasChildren): ?>
                        <button class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill shadow-none" 
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

            <!-- 90-Day Interactive Uptime Graph with Click-to-Inspect Modal -->
            <div class="uptime-graph" role="group" aria-label="90 Days Uptime History">
                <?php
                    for ($day = 89; $day >= 0; $day--):
                        $dayTime       = strtotime("-{$day} days");
                        $dayDate       = date('Y-m-d', $dayTime);
                        $formattedDate = date('M d, Y', $dayTime);

                        $dayData = $uptimeHistory[$mId][$dayDate] ?? null;

                        // Check if an Incident occurred on this day for this monitor (or global)
                        $hasIncident = false;
                        $incidentTitle = '';
                        foreach ($allIncidents as $inc) {
                            if (empty($inc['monitor_id']) || (int)$inc['monitor_id'] === $mId) {
                                $incStart = date('Y-m-d', strtotime($inc['created_at']));
                                $incEnd   = date('Y-m-d', strtotime($inc['updated_at']));
                                if ($dayDate >= $incStart && $dayDate <= $incEnd) {
                                    $hasIncident = true;
                                    $incidentTitle = $inc['title'];
                                    break;
                                }
                            }
                        }

                        // Check if a Maintenance occurred on this day for this monitor (or global)
                        $hasMaintenance = false;
                        $maintTitle = '';
                        foreach ($allMaintenances as $maint) {
                            if (empty($maint['monitor_id']) || (int)$maint['monitor_id'] === $mId) {
                                $mStart = date('Y-m-d', strtotime($maint['start_time']));
                                $mEnd   = date('Y-m-d', strtotime($maint['end_time']));
                                if ($dayDate >= $mStart && $dayDate <= $mEnd) {
                                    $hasMaintenance = true;
                                    $maintTitle = $maint['title'];
                                    break;
                                }
                            }
                        }

                        // PRIORITY HIERARCHY FOR COLORS: Blackout > Outage > Incident > Maintenance > Degraded > Operational
                        if ($day === 0 && $isDown) {
                            $barClass = 'uptime-outage';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Major Outage";
                        } elseif ($dayData && $dayData['blackout'] > 0) {
                            $barClass = 'uptime-blackout';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #0f172a;'>⬛</span> System Blackout (Machine Offline)";
                        } elseif ($dayData && $dayData['down'] > 0) {
                            $barClass = 'uptime-outage';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Outage Detected";
                        } elseif ($hasIncident) {
                            // ORANGE BAR: Declared Incident
                            $barClass = 'uptime-incident';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #f97316;'>●</span> Incident: " . htmlspecialchars($incidentTitle);
                        } elseif ($hasMaintenance) {
                            // AZURE BLUE BAR: Scheduled Maintenance
                            $barClass = 'uptime-maintenance';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #0ea5e9;'>●</span> Maintenance: " . htmlspecialchars($maintTitle);
                        } elseif ($dayData && $dayData['up'] < $dayData['total']) {
                            $barClass = 'uptime-degraded';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #f59e0b;'>●</span> Performance Degraded";
                        } elseif ($dayData && $dayData['total'] > 0) {
                            $barClass = 'uptime-operational';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                        } else {
                            $barClass = 'uptime-operational';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> Operational";
                        }

                        // Payload for the click-to-inspect daily modal
                        $modalPayload = [
                            'date'        => $formattedDate,
                            'monitor'     => $monitor['name'],
                            'checks'      => $dayData['total'] ?? 0,
                            'blackouts'   => $dayData['blackout'] ?? 0,
                            'outages'     => $dayData['down'] ?? 0,
                            'incident'    => $hasIncident ? $incidentTitle : null,
                            'maintenance' => $hasMaintenance ? $maintTitle : null
                        ];
                        $jsonPayload = htmlspecialchars(json_encode($modalPayload), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="uptime-bar <?= $barClass ?>" 
                         data-bs-toggle="tooltip" 
                         data-bs-placement="top" 
                         data-bs-html="true" 
                         title="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
                         onclick="openDayDetailModal(<?= $jsonPayload ?>)">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="d-flex justify-content-between text-muted small mt-2">
                <span>90 days ago</span>
                <span class="fw-semibold text-dark"><?= number_format($uptimePct, 2) ?>% uptime</span>
                <span>Today</span>
            </div>

            <!-- Sub-services Drawer -->
            <?php if ($hasChildren): ?>
                <div class="collapse mt-3 pt-3 border-top" id="subservices-<?= $monitor['id'] ?>">
                    <div class="ps-3 border-start border-3 border-primary-subtle d-flex flex-column gap-3">
                        <?php foreach ($monitor['children'] as $child): ?>
                            <?php
                                $childDown     = ($child['current_status'] === 'down');
                                $childDegraded = ($child['current_status'] === 'degraded');
                                $childUptime   = (float)($child['uptime_percentage'] ?? 100.00);
                                $cId           = (int)$child['id'];
                            ?>
                            <div class="bg-light p-3 rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold text-dark small">
                                        <i class="bi bi-arrow-return-right me-1 text-muted"></i>
                                        <?= htmlspecialchars($child['name']) ?>
                                    </span>
                                    <span class="badge bg-<?= $child['current_status'] === 'operational' ? 'success' : ($child['current_status'] === 'degraded' ? 'warning text-dark' : 'danger') ?> py-1 px-2" style="font-size: 10px;">
                                        <?= strtoupper($child['current_status']) ?>
                                    </span>
                                </div>

                                <!-- Sub-service 90-Day Mini Bar -->
                                <div class="uptime-graph" style="height: 18px;" role="group">
                                    <?php
                                        for ($cDay = 89; $cDay >= 0; $cDay--):
                                            $cDayTime  = strtotime("-{$cDay} days");
                                            $cDayDate  = date('Y-m-d', $cDayTime);
                                            $cDate     = date('M d, Y', $cDayTime);

                                            $cData = $uptimeHistory[$cId][$cDayDate] ?? null;

                                            if ($cDay === 0 && $childDown) {
                                                $cClass = 'uptime-outage';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #ef4444;'>●</span> Outage";
                                            } elseif ($cData && $cData['blackout'] > 0) {
                                                $cClass = 'uptime-blackout';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #0f172a;'>⬛</span> Blackout";
                                            } elseif ($cData && $cData['down'] > 0) {
                                                $cClass = 'uptime-outage';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #ef4444;'>●</span> Outage";
                                            } else {
                                                $cClass = 'uptime-operational';
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

    $primaryMonitors   = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 1)));
    $secondaryMonitors = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 0)));
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
</div>

<!-- Modal 1: Daily History Inspector (Opens when clicking any 90-day bar) -->
<div class="modal fade" id="dayDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="dayModalDateTitle">Daily Report</h5>
                    <small class="text-muted" id="dayModalMonitorName">Service Name</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Status List Overview -->
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Automated Checks Executed:
                        <span class="badge bg-secondary" id="dayModalChecksCount">0</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Outages / Downtime Detected:
                        <span class="badge bg-danger" id="dayModalOutagesCount">0</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        System Blackouts / Server Offline:
                        <span class="badge bg-dark" id="dayModalBlackoutsCount">0</span>
                    </li>
                </ul>

                <!-- Incidents Breakdown -->
                <div id="dayModalIncidentBox" class="d-none alert alert-warning border-warning mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> Incident Active:</h6>
                    <div id="dayModalIncidentTitle" class="small fw-semibold text-dark">Incident description</div>
                </div>

                <!-- Maintenances Breakdown -->
                <div id="dayModalMaintBox" class="d-none alert alert-info border-info mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-tools me-1 text-info"></i> Maintenance Window:</h6>
                    <div id="dayModalMaintTitle" class="small fw-semibold text-dark">Maintenance description</div>
                </div>

                <div id="dayModalCleanMsg" class="alert alert-success d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                    <div>
                        <strong>100% Operational</strong>
                        <div class="small">No disruptions, outages, or incidents were reported on this day.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Unified 2-in-1 Subscribe / Unsubscribe -->
<div class="modal fade" id="subscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <ul class="nav nav-pills card-header-pills w-100" role="tablist">
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link active w-100 fw-bold" data-bs-toggle="pill" data-bs-target="#tabSubscribe" type="button">
                            <i class="bi bi-bell me-1"></i> Subscribe
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center">
                        <button class="nav-link w-100 fw-bold text-danger" data-bs-toggle="pill" data-bs-target="#tabUnsubscribe" type="button">
                            <i class="bi bi-bell-slash me-1"></i> Unsubscribe
                        </button>
                    </li>
                </ul>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 tab-content">
                <!-- TAB 1: SUBSCRIBE FORM -->
                <div class="tab-pane fade show active" id="tabSubscribe">
                    <form action="/subscribe" method="POST">
                        <input type="hidden" name="monitor_id" id="modalMonitorId" value="">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Target Service:</label>
                            <div class="p-2 bg-light rounded-3 border fw-bold text-dark small d-flex align-items-center gap-2" id="modalTargetServiceName">
                                <i class="bi bi-hdd-network text-primary"></i> All Core & Platform Services
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Your Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                            <i class="bi bi-check2-circle me-1"></i> Confirm & Subscribe
                        </button>
                    </form>
                </div>

                <!-- TAB 2: UNSUBSCRIBE FORM -->
                <div class="tab-pane fade" id="tabUnsubscribe">
                    <form action="/subscribe/request-unsubscribe" method="POST">
                        <div class="alert alert-light border small text-muted mb-3">
                            <i class="bi bi-shield-lock text-danger me-1"></i>
                            Enter your email below to receive a secure <strong>one-click confirmation link valid for 60 minutes</strong> to remove all your subscriptions.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Your Registered Email</label>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold">
                            <i class="bi bi-envelope-x me-1"></i> Send Unsubscribe Link
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openDayDetailModal(data) {
    document.getElementById('dayModalDateTitle').textContent = 'Daily Report: ' + data.date;
    document.getElementById('dayModalMonitorName').textContent = data.monitor;
    document.getElementById('dayModalChecksCount').textContent = data.checks;
    document.getElementById('dayModalOutagesCount').textContent = data.outages;
    document.getElementById('dayModalBlackoutsCount').textContent = data.blackouts;

    const incidentBox = document.getElementById('dayModalIncidentBox');
    const maintBox = document.getElementById('dayModalMaintBox');
    const cleanMsg = document.getElementById('dayModalCleanMsg');

    let hasEvent = false;

    if (data.incident) {
        document.getElementById('dayModalIncidentTitle').textContent = data.incident;
        incidentBox.classList.remove('d-none');
        hasEvent = true;
    } else {
        incidentBox.classList.add('d-none');
    }

    if (data.maintenance) {
        document.getElementById('dayModalMaintTitle').textContent = data.maintenance;
        maintBox.classList.remove('d-none');
        hasEvent = true;
    } else {
        maintBox.classList.add('d-none');
    }

    if (hasEvent || data.outages > 0 || data.blackouts > 0) {
        cleanMsg.classList.add('d-none');
    } else {
        cleanMsg.classList.remove('d-none');
    }

    new bootstrap.Modal(document.getElementById('dayDetailModal')).show();
}

function openSubscriptionModal(monitorId, monitorName) {
    document.getElementById('modalMonitorId').value = monitorId ? monitorId : '';
    document.getElementById('modalTargetServiceName').innerHTML = '<i class="bi bi-hdd-network text-primary"></i> ' + monitorName;

    const subscribeTabTrigger = document.querySelector('#subscriptionModal .nav-link[data-bs-target="#tabSubscribe"]');
    if (subscribeTabTrigger) {
        bootstrap.Tab.getOrCreateInstance(subscribeTabTrigger).show();
    }

    new bootstrap.Modal(document.getElementById('subscriptionModal')).show();
}
</script>
