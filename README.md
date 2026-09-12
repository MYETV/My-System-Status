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

**My System Status** combines automated background heartbeat checks (HTTP, Ping, Port, SSL) with an incident communication platform (scheduled maintenance calendar, public uptime bars, public email subscriptions, and AI-assisted post-mortems).

It is designed to be lightweight, modular (pure MVC without bloated dependencies), secure, and ready for deployment on both shared hosting and VPS environments.

---

## ✄ Key Features

- ⚡ **Multi-Protocol Monitoring Engine**: Concurrently probe targets via HTTP/HTTPS (curl_multi), Ping (ICMP), TCP Ports (sockets), and SSL Certificate Expiry validation.
- **AI Incident Assistant**: Automatically analyze probe error traces and draft clear, non-technical public incident summaries using local Ollama or Google Gemini API.
- 🔐 **Enterprise Security**:
  - **Two-Factor Authentication (2FA)**: Pure RFC 6238 TOTP engine compatible with Google Authenticator, Microsoft Authenticator, Authy, and 1Password.
  - **Cloudflare Turnstile**: Zero-friction bot protection on administrative login endpoints.
  - **Built-in Rate Limiter**: Automatic IP throttling and lockout against brute-force attacks.
- **OAuth 2.0 SSO**: Native support for MYETV Developer API, Google, Microsoft Azure AD, and Facebook Login.
- 💔 **Maintenance Scheduling with FullCalendar**: Schedule future downtime windows and view them interactively on a responsive calendar.
- **Subscriber Notifications**:
  - Public visitors can subscribe via email without registering an account.
  - Built-in RFC-compliant SMTP mailer with native STARTTLS support.
  - One-click cryptographic unsubscribe tokens.
- 🔌 **Plugins & External Feeds**:
  - *1-Click External Status Importer**: Synchronize public health status from Cloudflare, GitHub, and Stripe.
  - **Discord Alert Webhooks**: Dispatch rich embed notifications to Discord channels on outages.
  - **LibreTranslate i18n Engine**: Auto-translate language JSON files on the fly.
- **Two-Layer Timezone Engine**: Global platform default (Admin Settings) + User personal timezone auto-detected from browser.
- 👱 **Embeddable Alert SDK**: Lightweight JavaScript snippet (embed.js) to display active incidents and maintenance banners on external websites.
- 🔴 **Integrated GitHub Auto-Updater**: Detect and install new versions directly from GitHub Releases with automatic database migrations.

---

## 📋 System Requirements

- PHP: 8.3 or higher
- PHP Extensions: pdo_mysql, curl, openssl, json
- Database: MySQL 5.7+ / 8.0+ or MariaDB 10.3+
- Web Server: Apache (mod_rewrite enabled) or Nginx
- Permissions: Write permissions on root directory, /config, /install, and /languages

---

## 🛦 Installation Guide

### 1. Clone the Repository (in your own directory)
group: git clone https://github.com/OskarCosimo/My-System-Status.git /var/www/your-domain.com
cd /var/www/your-domain.com

### 2. Set Directory Permissions
sudo chown -R www-data:www-data /var/www/your-domain.com
sudo chmod -R 755 /var/www/your-domain.com

### 3. Run the Web Installer	
Open your browser and navigate to:
https://mysystemstatus.com/install/

Follow the graphical wizard to configure your MySQL credentials and create the Super Administrator account.

### 4. Setup Background Monitoring (Cron)
Add this entry to your server's crontab to run probe checks every minute:
command: crontab -e

add line:
* * * * * php /var/www/mysystemstatus/cron/runner.php >/dev/null 2>&1

---

Ecco il paragrafo dedicato alla configurazione del **Database** da inserire nel tuo `README.md`:

---

Hai perfettamente ragione! Abbiamo creato il web installer grafico apposta per evitare a chiunque di dover toccare file di configurazione o comandi da terminale. 

Togliamo qualsiasi procedura manuale. Ecco il paragrafo pulito e corretto al 100% per il `README.md`:

---

## 🗄️ Database Setup

My System Status requires a MySQL or MariaDB database. You do **not** need to manually import SQL files or edit PHP configuration files:

1. Create an empty database in MySQL:
   ```sql
   CREATE DATABASE mysystemstatus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
and assign and username and a password to it.

2. Open your browser and go to `/install/` (e.g. `https://your-domain.com/install/`).

3. Enter your database credentials and admin details in the graphical wizard. The installer will automatically:
   - Verify connection and write `config/database.php`.
   - Import the complete schema from `database/schema.sql`.
   - Create your Super Administrator account.
   - Lock the installer to prevent unauthorized access.

### Database Migrations
Future platform updates handle database changes automatically: any new migration script located in `database/migrations/` is executed by the integrated 1-click updater without manual intervention.
---

## 🔥 ModSecurity & WAF Note (Localhost AI Endpoints)

If your server runs ModSecurity with OWASP CRS, add the following to your ModSecurity custom rules to avoid SSRF false positives when configuring local Ollama endpoints:

SecRule SERVER_NAME "@streq your-domain.com" "id:210,phase:2,nolog,chain"
SecRule REQUEST_URI "@beginsWith /admin/settings" "t:none,ctl:ruleRemoveById=934110"

---

## 📩 Embeddable Alert Banner SDK

To display active incident or maintenance notices across external websites, simply include this script tag:

<script src="https://your-domain.com/assets/js/embed.js" async></script>

---

## 📜 License

Distributed under the MIT License.

Developed with with love by [https://oskarcosimo.com](https://oskarcosimo.com)
