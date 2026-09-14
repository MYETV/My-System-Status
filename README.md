# My System Status

<p align="center">
  <strong>An open-source, enterprise-grade, self-hosted status page and uptime monitoring platform.</strong><br>
  Built with modern PHP 8.3, MySQL, and Bootstrap 5.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 8.0+">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License MIT">
  <img src="https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square" alt="PRs Welcome">
</p>

---

## 🚀 Overview

**My System Status** combines multi-protocol background uptime monitoring (HTTP, Ping, Port, SSL) with a powerful incident communication platform (interactive maintenance calendar, public uptime charts, granular subscriber notifications, and AI-assisted post-mortems).

It is designed to be lightweight, modular (pure MVC without bloated dependencies), secure, and ready for deployment on shared hosting, bare-metal servers, or VPS environments.

---

## ✨ Key Features

### ⚡ Monitoring & Probes
- **Multi-Protocol Engine**: Concurrently probe targets via HTTP/HTTPS (`curl_multi`), ICMP Ping, TCP Ports (sockets), and SSL Certificate Expiry validation.
- **Dual Vantage Probing (Origin + Cloudflare Edge)**: Run checks locally and concurrently delegate probes to a Cloudflare Worker for global latency measurements.
- **Retroactive Blackout & Gap Detection**: If the host machine powers off or crashes, the system detects the missed execution window on reboot, backfills the outage log, and marks the day with a **Black bar (`#0f172a`)**.
- **Custom Sort Order & Core System Tiers**: Position primary systems (e.g. your core apps) at the top and secondary cloud dependencies below. The global health banner is driven strictly by Core services.

### 🌐 Edge Sentinel & 100% Offline Survival (Cloudflare Workers)
- **Autonomous Edge Sentinel (`edge/cloudflare-worker.js`)**: Runs independent cron checks from Cloudflare's edge. If your origin server dies, it immediately fires emergency Discord alerts and logs outage duration upon recovery.
- **Resilient Mirror Proxy (`edge/public-index-worker.js`)**: Powers a public subdomain (e.g., `status.myetv.tv`). When your origin server is online, it serves live data and saves a global snapshot in Cloudflare KV. If your origin server crashes or your modem is unplugged, it **keeps serving the status page with an emergency alert banner** so your users never see downtime!
- **Zero-Latency Edge Switchers**: Language and timezone toggles are executed directly at the Cloudflare Edge in <5ms.

### 🤖 AI Incident Assistant & DevOps REST API
- **AI Incident Drafts**: Analyze probe error traces and generate non-technical public summaries using local **Ollama** or **Google Gemini API**.
- **REST API Endpoints for CI/CD & Deploy Scripts**:
  - `POST /api/v1/maintenance/enable`: Automatically declare maintenance windows before running deployment scripts.
  - `POST /api/v1/maintenance/disable`: Automatically close and archive maintenance windows when deployments finish.
- **Hashed API Keys Management**: Generate, label, and revoke SHA-256 hashed API keys from the admin panel (`/admin/api-keys`).

### 📊 Interactive 90-Day Visual Timeline
- **Rich Status Bars with Event Markers**:
  - 🟢 **Green**: 100% Operational.
  - 🔵 **Azure Down-Arrow (`▼`) Above Bar**: Scheduled Maintenance window on that day.
  - 🟠 **Orange Up-Arrow (`▲`) Below Bar**: Declared Incident on that day.
  - 🔴 **Red / Gradient**: Partial degradation or major probe outage.
  - ⚫ **Black**: System blackout / machine powered off.
- **Click-to-Inspect Daily Modal**: Click on any day to open a breakdown showing exact timestamps, checks executed, maintenance windows, and chronological incident timeline updates.

### 🔐 Enterprise Security & Authentication
- **Two-Factor Authentication (2FA)**: Pure RFC 6238 TOTP engine compatible with Google Authenticator, Microsoft Authenticator, Authy, and 1Password.
- **Cloudflare Turnstile**: Zero-friction bot protection on login endpoints.
- **Anti-Brute Force Rate Limiter**: Automatic IP throttling and lockout on suspicious login attempts.
- **OAuth 2.0 SSO**: Built-in support for MYETV Developer API, Google, Microsoft Azure AD, and Facebook Login.

### 📧 Granular Subscriptions & Magic Links
- **Per-Probe or Global Subscriptions**: Visitors can subscribe to all services or exclusively to a single component (e.g., only Core API).
- **Time-Limited (60 min) Magic Unsubscribe Link**: Users can enter their email to receive a secure, one-click link valid for 1 hour that purges all their active subscriptions.
- **Responsive HTML Emails**: Clean corporate cards with direct unsubscribe footers.

### 🔌 Plugins & Cloud Feeds
- **1-Click Cloud Status Importers**: Monitor health status for **Cloudflare**, **Amazon AWS**, **Microsoft Azure**, **Stripe**, **PayPal**, and **GitHub**.
- **Cloudflare Zero Trust Tunnel Monitoring**: Automatically query Cloudflare API v4 to verify the live health of private `cloudflared` tunnels.
- **Discord Alert Webhooks**: Dispatch rich embed notifications to Discord channels on outages.
- **LibreTranslate i18n Engine**: Automatically translate master language keys (`en.json` &rarr; `it.json`, `es.json`, `fr.json`) on the fly.

---

## 📋 System Requirements

- **PHP**: `8.3` or higher
- **PHP Extensions**: `pdo_mysql`, `curl`, `openssl`, `json`
- **Database**: MySQL `5.7+` / `8.0+` or MariaDB `10.3+`
- **Web Server**: Apache (`mod_rewrite` enabled) or Nginx
- **Permissions**: Write permissions on root directory, `/config`, `/install`, and `/languages`

---

## 🛠️ Installation Guide

### 1. Clone the Repository
```bash
git clone https://github.com/OskarCosimo/My-System-Status.git /var/www/your-domain.com
cd /var/www/your-domain.com
```

### 2. Set Directory Permissions
Ensure the webserver user (`www-data` on Debian/Ubuntu, `apache` on RHEL/CentOS) has write access:
```bash
sudo chown -R www-data:www-data /var/www/your-domain.com
sudo chmod -R 755 /var/www/your-domain.com
```

### 3. Run the Web Installer
Open your browser and navigate to:
```text
https://your-domain.com/install/
```
Follow the graphical wizard to verify your environment, configure your MySQL credentials, and create the Super Administrator account.

### 4. Setup Background Monitoring (Cron)
Add this entry to your server's crontab to execute probe checks every minute:
```bash
crontab -e
```
Add the following line:
```cron
* * * * * php /var/www/your-domain.com/cron/runner.php >/dev/null 2>&1
```

---

## 🗄️ Database Setup & Migrations

1. Create an empty database in MySQL and assign a user with full privileges:
   ```sql
   CREATE DATABASE mysystemstatus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Navigate to `/install/` in your browser. The web wizard will automatically import `database/schema.sql`, write `config/database.php`, and lock the installer.

### Safe Incremental Migrations (`migrate.php`)
Platform updates handle database migrations safely and automatically through `migrate.php`. 
If you update manually via `git pull`, simply run:
```bash
php /var/www/your-domain.com/migrate.php
```

---

## 🤖 REST API Automation (Deployment Scripts)

You can automate maintenance windows from remote deployment scripts or CI/CD pipelines (e.g. GitHub Actions, Bash, Node.js).

Generate an API key in **Admin Panel &rarr; API Keys**, then authenticate via the `Authorization: Bearer <KEY>` header:

### 1. Enable Maintenance Mode
```bash
curl -X POST https://your-domain.com/api/v1/maintenance/enable \
  -H "Authorization: Bearer mss_live_xxxxxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"title": "Deploying Upgrade", "description": "Maintenance mode activated via deployment automation."}'
```

### 2. Disable Maintenance Mode (Close active windows)
```bash
curl -X POST https://your-domain.com/api/v1/maintenance/disable \
  -H "Authorization: Bearer mss_live_xxxxxxxxxxxxxxxxxxxx"
```

---

## 📢 Embeddable Alert Banner SDK

To display active incident or maintenance popups across your public websites, add this single script before the closing `</body>` tag:

```html
<script src="https://your-domain.com/assets/js/embed.js" defer></script>
```

- **Nice Style**: Appears as a sleek, non-intrusive floating card in the bottom-left corner with a pulsating alert dot.
- **Zero Hardcoded URLs**: Dynamically detects whichever domain it is served from.
- **Session Memory**: If dismissed by the user, it remains closed for the rest of their session.
- **Customizable**: Use `data-position="bottom-right"` to display it on the bottom right.

---

## 🛡️ ModSecurity & WAF Note

If your server runs **ModSecurity with OWASP CRS**, add the following rule to your custom ModSecurity configuration to avoid false positives on administrative settings updates (e.g. internal IPs for LibreTranslate, local Ollama endpoints):

```apache
SecRule SERVER_NAME "@streq your-domain.com" "id:210,phase:2,nolog,chain"
SecRule REQUEST_URI "@beginsWith /admin/settings" "t:none,ctl:ruleEngine=Off"
```

---

## 🔄 Self-Updater

**My System Status** includes an integrated 1-click updater:
1. Navigate to **Admin Panel &rarr; Updates**.
2. The platform checks GitHub Releases against your local version in `config/app.php`.
3. The engine performs a pre-flight write permissions check on all critical paths to ensure the update won't fail halfway through.
4. When you click **Download & Install**, it downloads the release archive, updates the core files, flushes PHP OPcache, and executes `migrate.php` automatically.

---

## 📄 License

Distributed under the **MIT License**.

Developed with ❤️ by [Oskar Cosimo](https://oskarcosimo.com) & [MYETV](https://www.myetv.tv)
