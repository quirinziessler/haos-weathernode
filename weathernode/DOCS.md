# Home Assistant Add-on: WeatherNode

[WeatherNode](https://www.weathernode.dev/) is an open-source, self-hosted weather dashboard and station aggregator that connects to your personal weather station (Ecowitt, Davis WeatherLink, WeatherFlow Tempest, Ambient Weather, Netatmo, WeeWX, etc.) and combines live station readings with forecasts, radar, astronomy, and air quality.

## Installation

1. Add this repository to your Home Assistant Add-on Store:
   `https://github.com/quirinziessler/haos-weathernode`
2. Locate **WeatherNode** in the Add-on Store and click **Install**.
3. Open the **Configuration** tab in the Home Assistant UI before starting the add-on.

## Configuration Options (Home Assistant UI)

All settings are manageable directly in the Home Assistant Configuration UI:

| Option | Type | Required | Description |
|---|---|---|---|
| `APP_URL` | `url` | **Yes** | Full access URL including port (e.g. `http://homeassistant.local:8080` or `http://192.168.1.100:8080`). |
| `APP_KEY` | `string` | **Yes** | 32-byte base64 encryption key (format: `base64:...`). |
| `APP_NAME` | `string` | No | Dashboard name displayed in header and page titles. Default: `WeatherNode`. |
| `APP_TIMEZONE` | `string` | No | Timezone for sunrise/sunset calculations and timestamps (e.g. `UTC`, `Europe/Berlin`, `America/New_York`). |
| `APP_LOCALE` | `list` | No | Language (`en`, `de`, `fr`, `es`, `it`, `nl`). Default: `en`. |
| `APP_ENV` | `list` | No | Environment mode (`production`, `local`, `testing`). Default: `production`. |
| `APP_DEBUG` | `bool` | No | Enables detailed debug traces. Keep `false` in production. |
| `LOG_CHANNEL` | `list` | No | Log driver (`stderr`, `stack`, `single`, `daily`). `stderr` logs directly to Home Assistant add-on logs. |
| `ADMIN_EMAIL` | `email` | No | Email address to automatically provision the first administrator account. |
| `ADMIN_PASSWORD` | `password` | No | Password for the first administrator account. |
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

## Storage and Persistence

- **Database**: SQLite database is stored at `/data/weathernode.sqlite`, located inside Home Assistant's persistent add-on storage. Your weather records, configurations, and station pairings persist across updates and restarts.
- **Scheduler**: The Laravel task scheduler runs automatically inside the container (`DOCKER_RUN_SCHEDULER: true`), polling sensors and updating weather caches every minute.
