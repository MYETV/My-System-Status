/**
 * path: public/assets/js/embed.js
 * My System Status - Resilient Failover Alert Widget SDK
 * 
 * =============================================================================
 * HOW TO EMBED THIS WIDGET ON ANY WEBSITE
 * =============================================================================
 * 
 * 1. Standard Setup (Queries your status server directly):
 *    <script src="https://your-status-domain.com/assets/js/embed.js" defer></script>
 * 
 * 2. High-Availability Setup (Automatically fails over to Edge Worker if origin is offline):
 *    <script 
 *      src="https://your-status-domain.com/assets/js/embed.js" 
 *      data-fallback="https://your-edge-mirror.workers.dev"
 *      onerror="this.onerror=null;this.src='https://your-edge-mirror.workers.dev/assets/js/embed.js';"
 *      defer>
 *    </script>
 * 
 * =============================================================================
 * OPTIONAL CONFIGURATION ATTRIBUTES:
 * =============================================================================
 * - data-fallback:  (Optional) Fallback edge mirror URL used if the primary server is down.
 * - data-position:  (Optional) "bottom-left" (default) or "bottom-right".
 * - data-cache-ttl: (Optional) Browser cache duration in seconds in sessionStorage (default: "60").
 * =============================================================================
 */
(function () {
    // 1. Locate current script element and extract configuration attributes
    const currentScript = document.currentScript || (function () {
        const scripts = document.getElementsByTagName('script');
        for (let i = scripts.length - 1; i >= 0; i--) {
            if (scripts[i].src && scripts[i].src.includes('embed.js')) {
                return scripts[i];
            }
        }
        return null;
    })();

    if (!currentScript || !currentScript.src) return;

    const scriptUrl    = new URL(currentScript.src);
    const PRIMARY_URL  = scriptUrl.origin;
    const FALLBACK_URL = (currentScript.getAttribute('data-fallback') || '').replace(/\/+$/, '');
    const POSITION     = currentScript.getAttribute('data-position') || 'bottom-left';
    const CACHE_TTL    = parseInt(currentScript.getAttribute('data-cache-ttl') || '60', 10);

    // 2. Client-side Smart Cache (Prevents hammering origin server/worker on every page navigation)
    function getCachedAlerts() {
        try {
            const raw = sessionStorage.getItem('mss_alerts_cache');
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (Date.now() - parsed.timestamp < CACHE_TTL * 1000) {
                return parsed.data;
            }
        } catch (e) {}
        return null;
    }

    function setCachedAlerts(data) {
        try {
            sessionStorage.setItem('mss_alerts_cache', JSON.stringify({
                timestamp: Date.now(),
                data: data
            }));
        } catch (e) {}
    }

    // Helper: fetch with strict timeout
    async function fetchWithTimeout(url, timeoutMs = 2500) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
        try {
            const res = await fetch(url, {
                method: 'GET',
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });
            clearTimeout(timeoutId);
            if (res.ok) {
                return await res.json();
            }
        } catch (e) {
            clearTimeout(timeoutId);
        }
        return null;
    }

    // 3. Resilient Fetch: Primary Origin -> Fallback Edge Worker
    async function fetchStatusWithFailover() {
        const cached = getCachedAlerts();
        if (cached) {
            return cached;
        }

        let alertData = null;
        let activeSourceOrigin = PRIMARY_URL;

        // Step A: Attempt Primary Server (Unlimited capacity, free)
        alertData = await fetchWithTimeout(`${PRIMARY_URL}/api/v1/alerts`, 2000);

        // Step B: If Primary Server is OFFLINE and Fallback Edge Worker is provided -> Failover!
        if (!alertData && FALLBACK_URL) {
            alertData = await fetchWithTimeout(`${FALLBACK_URL}/api/v1/alerts`, 3000);
            if (alertData) {
                activeSourceOrigin = FALLBACK_URL;
            }
        }

        if (alertData) {
            alertData._sourceOrigin = activeSourceOrigin;
            setCachedAlerts(alertData);
        }

        return alertData;
    }

    async function checkStatusAlerts() {
        try {
            const data = await fetchStatusWithFailover();
            if (data && data.status === 'alert') {
                renderAlertPopup(data, data._sourceOrigin || PRIMARY_URL);
            }
        } catch (e) {}
    }

    function renderAlertPopup(data, originUrl) {
        const activeIncident    = (data.incidents && data.incidents.length > 0) ? data.incidents[0] : null;
        const activeMaintenance = (data.maintenances && data.maintenances.length > 0) ? data.maintenances[0] : null;

        const event = activeIncident || activeMaintenance;
        if (!event) return;

        const isIncident = !!activeIncident;
        const eventId = (isIncident ? 'inc_' : 'maint_') + event.id;

        if (sessionStorage.getItem('mss_dismissed_' + eventId)) {
            return;
        }

        const accentColor  = isIncident ? '#ef4444' : '#0ea5e9';
        const badgeBgColor = isIncident ? 'rgba(239, 68, 68, 0.12)' : 'rgba(14, 165, 233, 0.12)';
        const badgeLabel   = isIncident ? 'ACTIVE INCIDENT' : 'SCHEDULED MAINTENANCE';
        const eventTitle   = event.title || 'Service Disruption';

        if (!document.getElementById('mss-embed-styles')) {
            const style = document.createElement('style');
            style.id = 'mss-embed-styles';
            style.textContent = `
                @keyframes mssSlideUp {
                    from { transform: translateY(100px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                @keyframes mssPulse {
                    0% { transform: scale(0.95); box-shadow: 0 0 0 0 ${isIncident ? 'rgba(239, 68, 68, 0.7)' : 'rgba(14, 165, 233, 0.7)'}; }
                    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(0, 0, 0, 0); }
                    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 0, 0, 0); }
                }
            `;
            document.head.appendChild(style);
        }

        const popup = document.createElement('div');
        popup.id = 'mss-status-widget';

        const posStyle = (POSITION === 'bottom-right') 
            ? 'right: 24px; left: auto;' 
            : 'left: 24px; right: auto;';

        popup.style.cssText = `
            position: fixed;
            bottom: 24px;
            ${posStyle}
            z-index: 2147483647;
            max-width: 380px;
            width: calc(100vw - 48px);
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid ${accentColor};
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            padding: 16px 18px;
            box-sizing: border-box;
            animation: mssSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        `;

        popup.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background-color: ${accentColor}; display: inline-block; animation: mssPulse 2s infinite;"></span>
                    <span style="font-size: 11px; font-weight: 700; color: ${accentColor}; background: ${badgeBgColor}; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.5px;">
                        ${badgeLabel}
                    </span>
                </div>
                <button type="button" id="mss-close-btn" aria-label="Close notification" style="background: none; border: none; font-size: 16px; color: #94a3b8; cursor: pointer; padding: 2px 6px; line-height: 1; border-radius: 4px;">✕</button>
            </div>
            <div style="font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.4; margin-bottom: 12px;">
                ${escapeHtml(eventTitle)}
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                <span style="color: #64748b;">Telemetry updates</span>
                <a href="${originUrl}" target="_blank" rel="noopener noreferrer" style="color: #0d6efd; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    View Status Page &rarr;
                </a>
            </div>
        `;

        document.body.appendChild(popup);

        document.getElementById('mss-close-btn').addEventListener('click', function () {
            sessionStorage.setItem('mss_dismissed_' + eventId, 'true');
            popup.style.transition = 'all 0.25s ease-out';
            popup.style.opacity = '0';
            popup.style.transform = 'translateY(20px)';
            setTimeout(() => popup.remove(), 250);
        });
    }

    function escapeHtml(str) {
        return str.replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkStatusAlerts);
    } else {
        checkStatusAlerts();
    }
})();
