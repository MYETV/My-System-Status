/**
 * path: public/assets/js/embed.js
 * My System Status - Modern Floating Widget SDK (Statuspage.io Style)
 * 
 * Usage:
 * Add this single script to the footer of any external website:
 * <script src="https://mysystemstatus.myetv.tv/assets/js/embed.js" defer></script>
 * 
 * Supports optional attributes:
 * - data-position="bottom-left" (default) or data-position="bottom-right"
 */
(function () {
    // 1. Auto-detect origin server URL dynamically from the <script src="..."> tag (Zero Hardcoded URLs!)
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

    const scriptUrl = new URL(currentScript.src);
    const BASE_URL  = scriptUrl.origin;
    const POSITION  = currentScript.getAttribute('data-position') || 'bottom-left';

    async function checkStatusAlerts() {
        try {
            const res = await fetch(`${BASE_URL}/api/v1/alerts`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;

            const data = await res.json();
            if (data && data.status === 'alert') {
                renderStatuspagePopup(data);
            }
        } catch (e) {
            // Silently fail on network issues to never impact the host site
        }
    }

    function renderStatuspagePopup(data) {
        const activeIncident    = (data.incidents && data.incidents.length > 0) ? data.incidents[0] : null;
        const activeMaintenance = (data.maintenances && data.maintenances.length > 0) ? data.maintenances[0] : null;

        const event = activeIncident || activeMaintenance;
        if (!event) return;

        const isIncident = !!activeIncident;
        const eventId = (isIncident ? 'inc_' : 'maint_') + event.id;

        // Don't show if visitor previously dismissed this specific alert during this session
        if (sessionStorage.getItem('mss_dismissed_' + eventId)) {
            return;
        }

        // Color theme: Red for Outages, Azure Blue for Maintenances
        const accentColor  = isIncident ? '#ef4444' : '#0ea5e9';
        const badgeBgColor = isIncident ? 'rgba(239, 68, 68, 0.12)' : 'rgba(14, 165, 233, 0.12)';
        const badgeLabel   = isIncident ? 'ACTIVE INCIDENT' : 'SCHEDULED MAINTENANCE';
        const eventTitle   = event.title || 'Service Disruption';

        // Inject scoped keyframe animation
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

        // Popup Card Container
        const popup = document.createElement('div');
        popup.id = 'mss-status-widget';

        const posStyle = (POSITION === 'bottom-right') 
            ? 'right: 24px; left: auto;' 
            : 'left: 24px; right: auto;';

        popup.style.cssText = `
            position: fixed;
            bottom: 24px;
            ${posStyle}
            z-index: 2147483647; /* Maximum possible z-index in browsers */
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
                    <!-- Pulsing status dot -->
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
                <a href="${BASE_URL}" target="_blank" rel="noopener noreferrer" style="color: #0d6efd; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    View Status Page &rarr;
                </a>
            </div>
        `;

        document.body.appendChild(popup);

        // Handle Close with session persistence
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
