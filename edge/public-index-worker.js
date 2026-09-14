/**
 * path: edge/public-index-worker.js
 * Cloudflare Worker: Resilient Edge Mirror & Fallback Status Page
 * 
 * Custom Domain: status.myetv.tv
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

    // 1. SECURITY REDIRECT: Never mirror or cache administrative or auth areas on the public edge!
    // Instantly redirects the user to the real origin server.
    if (url.pathname.startsWith("/admin") || url.pathname.startsWith("/auth") || url.pathname.startsWith("/install")) {
      return Response.redirect(originUrl + url.pathname + url.search, 302);
    }

    const targetUrl = originUrl + url.pathname + url.search;
    const cache = caches.default;
    const cacheKey = new Request(targetUrl, request);

    // 2. Try to fetch from origin server with a strict 5-second timeout
    let originResponse = null;
    let isOriginDown = false;

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 5000); // 5s timeout

      const reqHeaders = new Headers(request.headers);
      reqHeaders.set("X-Edge-Mirror", "MySystemStatus-Edge/1.0");

      originResponse = await fetch(targetUrl, {
        method: request.method,
        body: (request.method !== "GET" && request.method !== "HEAD") ? await request.clone().blob() : undefined,
        headers: reqHeaders,
        signal: controller.signal,
        redirect: "follow"
      });
      clearTimeout(timeoutId);

      // Treat 5xx status codes (500, 502, 503, 504, 521, 522) as origin down
      if (!originResponse || originResponse.status >= 500) {
        isOriginDown = true;
      }
    } catch (e) {
      isOriginDown = true;
    }

    // 3. SCENARIO A: Origin is ONLINE
    if (!isOriginDown && originResponse && originResponse.status < 400) {
      // Only cache GET requests (Cache API does not support POST caching)
      if (request.method === "GET") {
        const responseClone = originResponse.clone();
        const cachedHeaders = new Headers(responseClone.headers);
        cachedHeaders.set("Cache-Control", `public, max-age=${env.CACHE_TTL_SECONDS || 60}`);
        cachedHeaders.set("X-Snapshot-Saved", new Date().toISOString());

        const responseToCache = new Response(responseClone.body, {
          status: responseClone.status,
          statusText: responseClone.statusText,
          headers: cachedHeaders
        });

        // Store snapshot in background
        ctxOrWait(request, cache.put(cacheKey, responseToCache));
      }

      // Rewrite the Admin button link in the navbar to point to origin
      return new HTMLRewriter()
        .on("a[href='/admin']", {
          element(element) {
            element.setAttribute("href", `${originUrl}/admin`);
          }
        })
        .transform(originResponse);
    }

    // 4. SCENARIO B: Origin is OFFLINE / CRASHED -> Serve cached snapshot from Cloudflare Edge
    const cachedResponse = await cache.match(cacheKey);

    if (cachedResponse) {
      const snapshotTime = cachedResponse.headers.get("X-Snapshot-Saved") || "Recent";
      
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
        .on("a[href='/admin']", {
          element(element) {
            element.setAttribute("href", `${originUrl}/admin`);
          }
        })
        .transform(cachedResponse);
    }

    // 5. SCENARIO C: Complete Disaster (Origin down and no snapshot in cache yet)
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
  } catch (e) {}
}

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
