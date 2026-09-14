/**
 * path: edge/public-index-worker.js
 * Cloudflare Worker: Resilient Edge Mirror & Fallback Status Page
 * 
 * Routes/Custom Domain: status.myetv.tv
 * 
 * Environment Variables (Set in Cloudflare Dashboard):
 * - ORIGIN_URL: The real PHP status server URL (e.g. "https://mysystemstatus.myetv.tv")
 * - CACHE_TTL_SECONDS: Cache validity in seconds (default: "60")
 */

export default {
    async fetch(request, env) {
        const originUrl = (env.ORIGIN_URL || "").replace(/\/+$/, "");
        if (!originUrl) {
            return new Response("Configuration Error: ORIGIN_URL environment variable is missing.", { status: 500 });
        }

        const url = new URL(request.url);
        const targetUrl = originUrl + url.pathname + url.search;
        const cache = caches.default;
        const cacheKey = new Request(targetUrl, request);

        // 1. Try to fetch fresh HTML/assets from origin server with a strict 5-second timeout
        let originResponse = null;
        let isOriginDown = false;

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 seconds timeout

            const reqHeaders = new Headers(request.headers);
            reqHeaders.set("X-Edge-Worker", "MySystemStatus-Mirror/1.0");

            originResponse = await fetch(targetUrl, {
                method: request.method,
                headers: reqHeaders,
                signal: controller.signal,
                redirect: "follow"
            });
            clearTimeout(timeoutId);

            // Treat 500, 502, 503, 504, 521, 522 as server down
            if (!originResponse || originResponse.status >= 500) {
                isOriginDown = true;
            }
        } catch (e) {
            isOriginDown = true;
        }

        // 2. SCENARIO A: Origin is ONLINE -> Serve fresh and save snapshot in edge cache
        if (!isOriginDown && originResponse && originResponse.status < 400) {
            const responseClone = originResponse.clone();

            // Store in Cloudflare Edge Cache for 24 hours (used as emergency fallback)
            const cachedHeaders = new Headers(responseClone.headers);
            cachedHeaders.set("Cache-Control", `public, max-age=${env.CACHE_TTL_SECONDS || 60}`);
            cachedHeaders.set("X-Snapshot-Saved", new Date().toISOString());

            const responseToCache = new Response(responseClone.body, {
                status: responseClone.status,
                statusText: responseClone.statusText,
                headers: cachedHeaders
            });

            // Save asynchronously without blocking visitor
            ctxOrWait(request, cache.put(cacheKey, responseToCache));

            return originResponse;
        }

        // 3. SCENARIO B: Origin is OFFLINE / CRASHED -> Retrieve snapshot from edge cache
        const cachedResponse = await cache.match(cacheKey);

        if (cachedResponse) {
            const snapshotTime = cachedResponse.headers.get("X-Snapshot-Saved") || "Recent";

            // Inject Emergency Alert Banner directly into the cached HTML using HTMLRewriter
            const emergencyBannerHtml = `
        <div style="background-color: #0f172a; color: #ffffff; padding: 12px 16px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: bold; position: sticky; top: 0; z-index: 9999999; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2);">
          🚨 <span>Origin Status Server is Currently Offline &bull; Serving Cached Telemetry from ${snapshotTime}</span>
        </div>
      `;

            return new HTMLRewriter()
                .on("body", {
                    element(element) {
                        element.prepend(emergencyBannerHtml, { html: true });
                    }
                })
                .transform(cachedResponse);
        }

        // 4. SCENARIO C: Complete Disaster (Server is down and no snapshot in cache yet)
        return new Response(generateEmergencyFallbackHtml(originUrl), {
            status: 200,
            headers: { "Content-Type": "text/html; charset=UTF-8" }
        });
    }
};

function ctxOrWait(request, promise) {
    try {
        if (typeof request.waitUntil === "function") {
            request.waitUntil(promise);
        }
    } catch (e) { }
}

/**
 * Ultra-minimal emergency page if both origin and cache are empty
 */
function generateEmergencyFallbackHtml(originUrl) {
    return `
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status &mdash; Temporary Offline</title>
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
      .card { background: #1e293b; padding: 40px; border-radius: 12px; max-width: 480px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
      h2 { margin-top: 0; color: #ef4444; }
      p { color: #94a3b8; line-height: 1.6; }
      .badge { display: inline-block; background: #ef4444; color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 20px; }
    </style>
  </head>
  <body>
    <div class="card">
      <div class="badge">Major Server Incident</div>
      <h2>Status Origin Unreachable</h2>
      <p>The primary infrastructure monitoring origin (<code>${originUrl}</code>) is currently offline or unreachable.</p>
      <p>Engineers have been alerted automatically via edge sentinels. Please check back shortly.</p>
    </div>
  </body>
  </html>
  `;
}