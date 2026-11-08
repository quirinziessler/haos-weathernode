# Home Assistant Add-on: WeatherNode

[WeatherNode](https://www.weathernode.dev/) is an open-source, self-hosted weather dashboard and station aggregator that connects to your local weather station (Ecowitt, Davis WeatherLink, WeatherFlow Tempest, Ambient Weather, Netatmo, etc.) and combines live data with forecasts, radar, astronomy, and air quality.

## Installation

1. Add this repository to your Home Assistant Add-on Store:
   `https://github.com/quirinziessler/haos-weathernode`
2. Locate **WeatherNode** in the Add-on Store and click **Install**.
3. Open the **Configuration** tab before starting the add-on.

## Configuration

| Option | Type | Description |
|---|---|---|
| `APP_URL` | `url` | The URL you use to access WeatherNode, including protocol and port (e.g. `http://homeassistant.local:8080` or `http://192.168.1.100:8080`). |
| `APP_KEY` | `string` | **Required.** Laravel encryption key formatted as `base64:...` (32 bytes base64 encoded). |
| `APP_ENV` | `list` | Laravel application environment (`production`, `local`, `testing`). Default: `production`. |
| `APP_DEBUG` | `bool` | Enable debug mode for verbose error traces. Keep `false` in production. |
| `LOG_CHANNEL` | `list` | Log driver destination (`stderr`, `stack`, `single`, `daily`). Default: `stderr`. |
| `ADMIN_EMAIL` | `email` | Optional admin user email for automatic first-time provisioning. |
| `ADMIN_PASSWORD` | `password`| Optional admin user password for automatic first-time provisioning. |

### Generating an APP_KEY

Generate a key on Linux / macOS or via SSH:
```bash
echo "base64:$(openssl rand -base64 32)"
```
Copy and paste the entire string (including the `base64:` prefix) into the `APP_KEY` configuration option.

> [!WARNING]
> Keep your `APP_KEY` backed up! WeatherNode encrypts sensitive integration tokens with this key. If the key changes, previously saved encrypted values will become unreadable.

## Storage and Persistence

- **Database**: SQLite database is stored at `/data/weathernode.sqlite`, located inside Home Assistant's persistent add-on storage. Your weather records, configurations, and station pairings persist across updates and restarts.
- **Scheduler**: The Laravel task scheduler runs automatically inside the container (`DOCKER_RUN_SCHEDULER: true`), polling sensors and updating weather caches every minute.
