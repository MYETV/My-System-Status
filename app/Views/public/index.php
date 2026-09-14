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

    <!-- Reusable Monitor Row Function -->
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

                        $totalChecks = (int)($dayData['total'] ?? 0);
                        $downChecks  = (int)($dayData['down'] ?? 0);
                        $blackChecks = (int)($dayData['blackout'] ?? 0);
                        $upChecks    = (int)($dayData['up'] ?? max(0, $totalChecks - $downChecks - $blackChecks));

                        // Daily Uptime Percentage Calculation
                        $dailyUptimePct = ($totalChecks > 0) 
                            ? round(($upChecks / $totalChecks) * 100, 2) 
                            : 100.00;

                        // Check Incident on this day
                        $matchedIncident = null;
                        foreach ($allIncidents as $inc) {
                            if (empty($inc['monitor_id']) || (int)$inc['monitor_id'] === $mId) {
                                $incStart = date('Y-m-d', strtotime($inc['created_at']));
                                $incEnd   = date('Y-m-d', strtotime($inc['updated_at']));
                                if ($dayDate >= $incStart && $dayDate <= $incEnd) {
                                    $matchedIncident = $inc;
                                    break;
                                }
                            }
                        }

                        // Check Maintenance on this day
                        $matchedMaint = null;
                        foreach ($allMaintenances as $maint) {
                            if (empty($maint['monitor_id']) || (int)$maint['monitor_id'] === $mId) {
                                $mStart = date('Y-m-d', strtotime($maint['start_time']));
                                $mEnd   = date('Y-m-d', strtotime($maint['end_time']));
                                if ($dayDate >= $mStart && $dayDate <= $mEnd) {
                                    $matchedMaint = $maint;
                                    break;
                                }
                            }
                        }

                        // COLORING LOGIC WITH GRADIENT PERCENTAGE
                        if ($day === 0 && $isDown) {
                            $barClass = 'uptime-outage';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Major Outage (Active Down)";
                        } elseif ($blackChecks > 0) {
                            $barClass = 'uptime-blackout';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #0f172a;'>⬛</span> System Blackout ({$dailyUptimePct}% Uptime)";
                        } elseif ($matchedIncident) {
                            $barClass = 'uptime-incident';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #f97316;'>●</span> Incident: " . htmlspecialchars($matchedIncident['title']);
                        } elseif ($matchedMaint) {
                            $barClass = 'uptime-maintenance';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #0ea5e9;'>●</span> Maintenance: " . htmlspecialchars($matchedMaint['title']);
                        } elseif ($downChecks > 0) {
                            $downPct = round(100.0 - $dailyUptimePct, 2);
                            $redVisualPct = max(15, min(100, (int)$downPct));
                            $barClass = 'uptime-partial';
                            $barStyle = "style=\"background: linear-gradient(to top, #ef4444 0%, #ef4444 {$redVisualPct}%, #10b981 {$redVisualPct}%, #10b981 100%);\"";
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #ef4444;'>●</span> Downtime: {$downPct}% ({$dailyUptimePct}% Uptime)";
                        } elseif ($totalChecks > 0) {
                            $barClass = 'uptime-operational';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> 100% Operational";
                        } else {
                            $barClass = 'uptime-operational';
                            $barStyle = '';
                            $label = "<strong>{$formattedDate}</strong><br><span style='color: #10b981;'>●</span> Operational";
                        }

                        $eventsTimeline = $dayData['events'] ?? [];

                        // Extract first offline timestamp from all possible array key variations
                        $firstDownTime = null;
                        if (!empty($dayData['first_down_at'])) {
                            $firstDownTime = $dayData['first_down_at'];
                        } elseif (!empty($dayData['first_down_time'])) {
                            $firstDownTime = $dayData['first_down_time'];
                        } elseif (!empty($dayData['down_at'])) {
                            $firstDownTime = $dayData['down_at'];
                        } elseif (!empty($dayData['first_down'])) {
                            $firstDownTime = $dayData['first_down'];
                        } elseif (!empty($dayData['created_at']) && $downChecks > 0) {
                            $firstDownTime = $dayData['created_at'];
                        } elseif (!empty($eventsTimeline[0]['down_at'])) {
                            $firstDownTime = $eventsTimeline[0]['down_at'];
                        } elseif (!empty($eventsTimeline[0]['created_at'])) {
                            $firstDownTime = $eventsTimeline[0]['created_at'];
                        }

                        // Format timestamp nicely if full datetime string is provided
                        if ($firstDownTime && strtotime($firstDownTime) !== false) {
                            $firstDownTime = date('H:i:s', strtotime($firstDownTime));
                        }

                        $modalPayload = [
                            'date'          => $formattedDate,
                            'monitor'       => $monitor['name'],
                            'checks'        => $totalChecks,
                            'uptime_pct'    => $dailyUptimePct,
                            'blackouts'     => $blackChecks,
                            'outages'       => $downChecks,
                            'first_down_at' => $firstDownTime,
                            'events'        => $eventsTimeline,
                            'incident'      => $matchedIncident ? [
                                'title'       => $matchedIncident['title'],
                                'impact'      => strtoupper($matchedIncident['impact']),
                                'status'      => strtoupper($matchedIncident['status']),
                                'created_at'  => format_date($matchedIncident['created_at'], 'M d, Y H:i'),
                                'updated_at'  => format_date($matchedIncident['updated_at'], 'M d, Y H:i'),
                                'updates'     => array_map(fn($u) => [
                                    'status'  => strtoupper($u['status']),
                                    'message' => $u['message'],
                                    'time'    => format_date($u['created_at'], 'M d, H:i')
                                ], $matchedIncident['updates'] ?? [])
                            ] : null,
                            'maintenance'   => $matchedMaint ? [
                                'title'       => $matchedMaint['title'],
                                'description' => $matchedMaint['description'] ?? '',
                                'status'      => strtoupper(str_replace('_', ' ', $matchedMaint['status'])),
                                'start_time'  => format_date($matchedMaint['start_time'], 'M d, Y H:i'),
                                'end_time'    => format_date($matchedMaint['end_time'], 'M d, Y H:i T')
                            ] : null
                        ];
                ?>
                    <div class="uptime-bar <?= $barClass ?>" 
                         <?= $barStyle ?>
                         data-bs-toggle="tooltip" 
                         data-bs-placement="top" 
                         data-bs-html="true" 
                         title="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
                         data-day-payload='<?= htmlspecialchars(json_encode($modalPayload, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                         onclick="openDayDetailModalFromElement(this)">
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
                                                $cStyle = '';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #ef4444;'>●</span> Outage";
                                            } elseif ($cData && ($cData['blackout'] ?? 0) > 0) {
                                                $cClass = 'uptime-blackout';
                                                $cStyle = '';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #0f172a;'>⬛</span> Blackout";
                                            } elseif ($cData && ($cData['down'] ?? 0) > 0) {
                                                $cTotal = (int)($cData['total'] ?? 0);
                                                $cDown  = (int)($cData['down'] ?? 0);
                                                $cUpPct = $cTotal > 0 ? round((($cTotal - $cDown) / $cTotal) * 100, 2) : 95.0;
                                                $cDownPct = round(100.0 - $cUpPct, 2);
                                                $cRedPct  = max(15, min(100, (int)$cDownPct));
                                                $cClass = 'uptime-partial';
                                                $cStyle = "style=\"background: linear-gradient(to top, #ef4444 0%, #ef4444 {$cRedPct}%, #10b981 {$cRedPct}%, #10b981 100%);\"";
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #ef4444;'>●</span> Partial Outage ({$cUpPct}% Uptime)";
                                            } else {
                                                $cClass = 'uptime-operational';
                                                $cStyle = '';
                                                $cLabel = "<strong>{$cDate}</strong><br><span style='color: #10b981;'>●</span> Operational";
                                            }
                                    ?>
                                        <div class="uptime-bar <?= $cClass ?>" 
                                             <?= $cStyle ?>
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

<!-- Modal 1: Daily History Inspector (Rich Details with Timestamps & Timeline) -->
<div class="modal fade" id="dayDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="dayModalDateTitle">Daily Report</h5>
                    <small class="text-muted" id="dayModalMonitorName">Service Name</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Checks Counters Row -->
                <div class="row g-2 mb-4 text-center">
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="small text-muted">Daily Uptime</div>
                            <h5 class="fw-bold mb-0 text-success" id="dayModalUptimePct">100%</h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="small text-muted">Checks Executed</div>
                            <h5 class="fw-bold mb-0 text-dark" id="dayModalChecksCount">0</h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="small text-muted">Downtime Hits</div>
                            <h5 class="fw-bold mb-0 text-danger" id="dayModalOutagesCount">0</h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="small text-muted">System Blackouts</div>
                            <h5 class="fw-bold mb-0 text-dark" id="dayModalBlackoutsCount">0</h5>
                        </div>
                    </div>
                </div>

                <!-- Specific Downtime Timestamps & Disruption Log Box -->
                <div id="dayModalDowntimeBox" class="d-none card border-danger mb-3 shadow-sm">
                    <div class="card-header bg-danger bg-opacity-10 text-danger py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history fs-5"></i>
                        <span>Disruption Timeline & Specific Downtime Timestamps</span>
                    </div>
                    <div class="card-body">
                        <div class="timeline ps-3 border-start" id="dayModalDowntimeList">
                            <!-- Populated dynamically via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Detailed Incident Box -->
                <div id="dayModalIncidentBox" class="d-none card border-warning mb-3 shadow-sm">
                    <div class="card-header bg-warning bg-opacity-25 py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Reported Incident</span>
                        <div class="d-flex gap-1" id="dayModalIncBadges"></div>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold mb-2 text-dark" id="dayModalIncidentTitle">Title</h6>
                        <div class="small text-muted mb-3" id="dayModalIncTimestamps"></div>
                        
                        <!-- Timeline notes with timestamps -->
                        <h6 class="fw-bold small text-secondary mb-2">Chronological Updates:</h6>
                        <div class="timeline ps-3 border-start" id="dayModalIncTimeline"></div>
                    </div>
                </div>

                <!-- Detailed Maintenance Box -->
                <div id="dayModalMaintBox" class="d-none card border-info mb-3 shadow-sm">
                    <div class="card-header bg-info bg-opacity-25 py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark"><i class="bi bi-tools text-info me-1"></i> Scheduled Maintenance</span>
                        <span class="badge bg-info text-dark" id="dayModalMaintStatus">STATUS</span>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold mb-2 text-dark" id="dayModalMaintTitle">Title</h6>
                        <p class="small text-muted mb-2" id="dayModalMaintDesc"></p>
                        <div class="p-2 bg-light rounded border small text-dark" id="dayModalMaintWindow">
                            <i class="bi bi-clock me-1 text-primary"></i> <strong>Window:</strong> <span></span>
                        </div>
                    </div>
                </div>

                <!-- 100% Operational Clean Box -->
                <div id="dayModalCleanMsg" class="alert alert-success d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                    <div>
                        <strong>100% Operational</strong>
                        <div class="small">All systems operated normally with zero disruptions or outages recorded on this day.</div>
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
function openDayDetailModalFromElement(el) {
    const rawData = el.getAttribute('data-day-payload');
    if (!rawData) return;

    try {
        const data = JSON.parse(rawData);

        document.getElementById('dayModalDateTitle').textContent = 'Daily Report: ' + data.date;
        document.getElementById('dayModalMonitorName').textContent = data.monitor;
        document.getElementById('dayModalChecksCount').textContent = data.checks;
        document.getElementById('dayModalOutagesCount').textContent = data.outages;
        document.getElementById('dayModalBlackoutsCount').textContent = data.blackouts;

        const uptimeElem = document.getElementById('dayModalUptimePct');
        uptimeElem.textContent = data.uptime_pct + '%';
        if (data.uptime_pct >= 99.0) {
            uptimeElem.className = 'fw-bold mb-0 text-success';
        } else if (data.uptime_pct >= 90.0) {
            uptimeElem.className = 'fw-bold mb-0 text-warning';
        } else {
            uptimeElem.className = 'fw-bold mb-0 text-danger';
        }

        const downtimeBox  = document.getElementById('dayModalDowntimeBox');
        const downtimeList = document.getElementById('dayModalDowntimeList');
        const incidentBox  = document.getElementById('dayModalIncidentBox');
        const maintBox     = document.getElementById('dayModalMaintBox');
        const cleanMsg     = document.getElementById('dayModalCleanMsg');

        let hasEvent = false;

        // 1. Render Specific Downtime Events with Exact Offline Timestamps & Approximate Duration
        if (data.outages > 0 || data.blackouts > 0 || (data.events && data.events.length > 0)) {
            hasEvent = true;
            let dtHtml = '';

            if (data.events && data.events.length > 0) {
                data.events.forEach(ev => {
                    const downTimeStr = ev.down_at ? `Offline at <strong>${ev.down_at}</strong>` : 'Disruption timestamp recorded';
                    const durationStr = ev.duration || 'approx. 1-2 minutes';
                    const upTimeStr   = ev.up_at ? `&bull; Restored online at <strong>${ev.up_at}</strong>` : '';

                    dtHtml += `
                        <div class="mb-3 position-relative pb-2 border-bottom border-light">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> ${ev.type || 'OFFLINE'}</span>
                                <span class="text-dark small">${downTimeStr}</span>
                            </div>
                            <div class="small text-muted ps-2 d-flex align-items-center gap-2 flex-wrap">
                                <span><i class="bi bi-clock-history text-primary me-1"></i> Duration: <strong class="text-dark">${durationStr}</strong></span>
                                ${upTimeStr ? `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> ${upTimeStr}</span>` : ''}
                            </div>
                        </div>
                    `;
                });
            } else {
                const timeVal = data.first_down_at ? data.first_down_at : null;
                const timestampBadge = timeVal 
                    ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fs-6 py-1 px-2 mb-2 d-inline-block"><i class="bi bi-clock-fill me-1"></i> Offline at ${timeVal}</span>`
                    : `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 mb-2 d-inline-block"><i class="bi bi-clock me-1"></i> Disruption recorded</span>`;

                const timeText = timeVal 
                    ? `Offline at <strong>${timeVal}</strong> &bull; ` 
                    : '';

                const approxMinutes = Math.max(1, Math.round(data.outages * 0.5));
                const approxDurationText = approxMinutes === 1 
                    ? '1 minute' 
                    : (approxMinutes < 60 ? `~${approxMinutes} minutes` : `~${(approxMinutes/60).toFixed(1)} hours`);

                dtHtml = `
                    <div class="mb-2 position-relative">
                        <div>${timestampBadge}</div>
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <span class="badge bg-danger py-1 px-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> DISRUPTION REGISTERED</span>
                            <span class="badge bg-light text-dark border py-1 px-2">${data.outages} check hit(s) marked offline</span>
                        </div>
                        <div class="small text-dark mb-1 ps-1">
                            <i class="bi bi-clock-history text-primary me-1"></i> ${timeText}Estimated Downtime: <strong>Offline for ${approxDurationText}</strong> (${data.outages} offline checks out of ${data.checks} total)
                        </div>
                        <p class="mb-0 text-muted small ps-1">
                            Operational recovery completed. Daily service availability maintained at <strong>${data.uptime_pct}%</strong>.
                        </p>
                    </div>
                `;
            }

            downtimeList.innerHTML = dtHtml;
            downtimeBox.classList.remove('d-none');
        } else {
            downtimeBox.classList.add('d-none');
        }

        // 2. Render Incident with Timestamps and Updates Timeline
        if (data.incident) {
            hasEvent = true;
            document.getElementById('dayModalIncidentTitle').textContent = data.incident.title;
            
            document.getElementById('dayModalIncBadges').innerHTML = `
                <span class="badge bg-danger">${data.incident.impact}</span>
                <span class="badge bg-dark">${data.incident.status}</span>
            `;

            document.getElementById('dayModalIncTimestamps').innerHTML = `
                <i class="bi bi-calendar-event me-1"></i> <strong>Opened:</strong> ${data.incident.created_at} &bull; 
                <i class="bi bi-clock-history me-1"></i> <strong>Updated:</strong> ${data.incident.updated_at}
            `;

            let timelineHtml = '';
            if (data.incident.updates && data.incident.updates.length > 0) {
                data.incident.updates.forEach(u => {
                    timelineHtml += `
                        <div class="mb-2 position-relative">
                            <span class="badge bg-secondary me-1">${u.time}</span>
                            <strong class="small text-dark text-capitalize">${u.status}:</strong>
                            <p class="mb-0 text-muted small ps-2">${u.message}</p>
                        </div>
                    `;
                });
            } else {
                timelineHtml = '<small class="text-muted">No timeline notes posted.</small>';
            }
            document.getElementById('dayModalIncTimeline').innerHTML = timelineHtml;

            incidentBox.classList.remove('d-none');
        } else {
            incidentBox.classList.add('d-none');
        }

        // 3. Render Maintenance with Time Windows
        if (data.maintenance) {
            hasEvent = true;
            document.getElementById('dayModalMaintTitle').textContent = data.maintenance.title;
            document.getElementById('dayModalMaintDesc').textContent = data.maintenance.description || 'No description provided.';
            document.getElementById('dayModalMaintStatus').textContent = data.maintenance.status;
            document.querySelector('#dayModalMaintWindow span').textContent = `${data.maintenance.start_time} — ${data.maintenance.end_time}`;

            maintBox.classList.remove('d-none');
        } else {
            maintBox.classList.add('d-none');
        }

        // 4. Show clean 100% operational message if no events occurred
        if (hasEvent || data.outages > 0 || data.blackouts > 0) {
            cleanMsg.classList.add('d-none');
        } else {
            cleanMsg.classList.remove('d-none');
        }

        new bootstrap.Modal(document.getElementById('dayDetailModal')).show();
    } catch (e) {
        console.error('Error opening day detail modal:', e);
    }
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
