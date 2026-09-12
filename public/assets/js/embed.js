/**
 * path: public/assets/js/embed.js
 * MySystem Status Embed Banner SDK
 */
(function () {
    const STATUS_API = "https://mysystemstatus.myetv.tv/api/v1/alerts";

    async function checkStatus() {
        try {
            const res = await fetch(STATUS_API);
            const data = await res.json();

            if (data.status === 'alert') {
                renderAlert(data);
            }
        } catch (e) {
            console.error("MySystem Status Embed Error:", e);
        }
    }

    function renderAlert(data) {
        const item = data.incidents[0] || data.maintenances[0];
        const isMaintenance = !data.incidents[0];
        const bgColor = isMaintenance ? '#0dcaf0' : '#dc3545';

        const banner = document.createElement('div');
        banner.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%;
            background: ${bgColor}; color: #fff; text-align: center;
            padding: 10px 15px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px; font-weight: bold; z-index: 999999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2); display: flex;
            justify-content: space-between; align-items: center;
        `;

        banner.innerHTML = `
            <div style="flex: 1;">
                📢 <strong>${isMaintenance ? 'Scheduled Maintenance' : 'Active Incident'}:</strong> 
                ${item.title} — <a href="https://mysystemstatus.myetv.tv" target="_blank" style="color: #fff; text-decoration: underline;">View Status</a>
            </div>
            <button style="background: none; border: none; color: #fff; font-size: 18px; cursor: pointer;" onclick="this.parentElement.remove()">✕</button>
        `;
        document.body.prepend(banner);
    }

    window.addEventListener('DOMContentLoaded', checkStatus);
})();