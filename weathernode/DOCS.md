# Home Assistant Add-on: WeatherNode

[WeatherNode](https://www.weathernode.dev/) is an open-source, self-hosted weather dashboard and station aggregator that connects to your personal weather station (Ecowitt, Davis WeatherLink, WeatherFlow Tempest, Ambient Weather, Netatmo, WeeWX, etc.) and combines live station readings with forecasts, radar, astronomy, and air quality.

## Features

- **Home Assistant Ingress & Sidebar Access**: Seamlessly accessible directly from your Home Assistant left sidebar menu.
- **Direct Admin Access (Single Sign-On)**: Access WeatherNode from the Home Assistant menu as Administrator immediately without needing a separate user, extra login screen, or opening a second tab.
- **Persistent SQLite Storage**: Station records, settings, and historical logs are securely preserved in Home Assistant's `/data` volume.
- **Automatic Task Scheduler**: Laravel's background scheduler polls station sources and calculates forecasts continuously.
- **Automated Updates**: Integrated Renovate pipeline detects and builds upstream updates automatically.

## Installation

1. Add this repository to your Home Assistant Add-on Store:
   `https://github.com/quirinziessler/haos-weathernode`
2. Locate **WeatherNode** in the Add-on Store and click **Install**.
3. Enable **Show in sidebar** toggle on the add-on info page.
4. Open the **Configuration** tab in the Home Assistant UI, paste your generated `APP_KEY`, and click **Save**.
5. Click **Start** on the add-on info page.
6. Click **WeatherNode** in your Home Assistant sidebar to open the dashboard with immediate administrator access!

## Home Assistant Menu & Single Sign-On

When accessed through the Home Assistant sidebar menu (Ingress):
- The add-on detects the Home Assistant authenticated Ingress session.
- An administrator session is automatically authenticated without requiring any username or password.
- All internal links, stylesheets, scripts, and API calls are dynamically routed through the Home Assistant Ingress proxy.
- Iframe security headers (`X-Frame-Options` and CSP `frame-ancestors`) are automatically adapted to render cleanly inside the Home Assistant UI.

## Configuration Options (Home Assistant UI)

All settings are manageable directly in the Home Assistant Configuration UI:

| Option | Type | Required | Description |
|---|---|---|---|
| `APP_KEY` | `string` | **Yes** | 32-byte base64 encryption key (format: `base64:...`). |
| `APP_NAME` | `string` | No | Dashboard name displayed in header and page titles. Default: `WeatherNode`. |
| `APP_URL` | `url` | No | Optional external access URL if exposing via reverse proxy or direct port (e.g. `http://homeassistant.local:8080`). Not needed for Ingress. |
| `APP_TIMEZONE` | `string` | No | Timezone for sunrise/sunset calculations and timestamps (e.g. `UTC`, `Europe/Berlin`, `America/New_York`). |
| `APP_LOCALE` | `list` | No | Language (`en`, `de`, `fr`, `es`, `it`, `nl`). Default: `en`. |
| `APP_ENV` | `list` | No | Environment mode (`production`, `local`, `testing`). Default: `production`. |
| `APP_DEBUG` | `bool` | No | Enables detailed debug traces. Keep `false` in production. |
| `LOG_CHANNEL` | `list` | No | Log driver (`stderr`, `stack`, `single`, `daily`). `stderr` logs directly to Home Assistant add-on logs. |
| `ADMIN_EMAIL` | `email` | No | Custom email address for the administrator account. Default: `admin@homeassistant.local`. |
| `ADMIN_PASSWORD` | `password` | No | Custom password for the administrator account. If omitted, a random secure password is created (auto-login still works via Ingress). |
| `TRUSTED_PROXIES` | `string` | No | Proxies to trust for client IP and HTTPS headers (default: `*`). |
| `UPDATER_ENABLED` | `bool` | No | Keep `false` to hide in-app zip updater, as updates are managed automatically by Renovate. |

### Generating an APP_KEY

Generate a key on Linux / macOS or in a terminal:
```bash
echo "base64:$(openssl rand -base64 32)"
```
Copy and paste the entire string (including `base64:`) into the `APP_KEY` configuration field.

> [!WARNING]
> Keep your `APP_KEY` backed up! WeatherNode encrypts sensitive integration tokens and API keys with this key. If the key changes, previously saved encrypted values will become unreadable.

## Weather Station Webhooks (Port 8080)

If your weather station (e.g. Ecowitt, Ambient Weather, Davis) pushes data via HTTP webhooks over your local network:
- Port `8080` is exposed on your Home Assistant host.
- Point your weather station custom upload destination to `http://<homeassistant-ip>:8080/api/...`.

## Storage and Persistence

- **Database**: SQLite database is stored at `/data/weathernode.sqlite`, located inside Home Assistant's persistent add-on storage. Your weather records, configurations, and station pairings persist across updates and restarts.
- **Scheduler**: The Laravel task scheduler runs automatically inside the container (`DOCKER_RUN_SCHEDULER: true`), polling sensors and updating weather caches every minute.
