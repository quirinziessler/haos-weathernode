# WeatherNode Home Assistant Add-on Repository

A zero-maintenance, self-updating Home Assistant Add-on repository wrapping the official [WeatherNode](https://www.weathernode.dev/) container image (`ghcr.io/centauri/weathernode`).

[![Renovate enabled](https://img.shields.io/badge/renovate-enabled-brightgreen.svg)](https://renovatebot.com/)

---

## 🚀 Features

- **Home Assistant Sidebar Integration (Ingress)**: Access WeatherNode directly within the Home Assistant sidebar menu without leaving the interface.
- **Direct Administrator Access (Zero Extra Login)**: Automatically signs you in as Administrator via Home Assistant Ingress so you land directly on your dashboard without needing a separate user or an extra login screen.
- **Native Home Assistant UI Configuration**: Configure all add-on settings directly in the Home Assistant Web UI with built-in schema validation.
- **Persistent SQLite Backend**: Preconfigured with `DB_CONNECTION: sqlite` and persistent storage at `/data/weathernode.sqlite`, ensuring complete data retention across add-on restarts and upgrades.
- **Embedded Scheduler**: Runs Laravel task scheduling inside the add-on container (`DOCKER_RUN_SCHEDULER: true`) without requiring external cron jobs or secondary containers.
- **Station Webhooks & Direct Access**: Keeps port `8080` exposed so physical personal weather stations (Ecowitt, Ambient Weather, Davis, etc.) can send data directly over your LAN.
- **Automated Self-Updating (Renovate)**: Automated tracking of upstream `ghcr.io/centauri/weathernode` container releases. When a new upstream image is published, Renovate synchronizes both `weathernode/Dockerfile` and `weathernode/config.yaml` in a single pull request and auto-merges hands-free.

---

## 📦 Installation

1. In Home Assistant, navigate to **Settings** > **Add-ons** > **Add-on Store**.
2. Click the three dots (⋮) in the top-right corner and select **Repositories**.
3. Add this repository URL:
   ```text
   https://github.com/quirinziessler/haos-weathernode
   ```
4. Reload the store, click on **WeatherNode**, and select **Install**.
5. Enable **Show in sidebar** on the add-on info page.
6. Open the **Configuration** tab, paste a generated `APP_KEY` (e.g., `echo "base64:$(openssl rand -base64 32)"`), and click **Save**.
7. Start the add-on and click **WeatherNode** in your Home Assistant sidebar!

---

## 📂 Repository Structure

```
├── repository.json                                # Home Assistant Add-on repository metadata
├── renovate.json                                  # Renovate bot configuration with custom regex manager & automerge
├── README.md                                      # Repository documentation
└── weathernode/
    ├── Dockerfile                                 # Wrapper Dockerfile extending ghcr.io/centauri/weathernode
    ├── config.yaml                                # Home Assistant Add-on configuration, Ingress & UI schema
    ├── run.sh                                     # Entrypoint wrapper injecting options and provisioning admin
    ├── HomeAssistantIngressMiddleware.php         # Ingress subpath URL generator & admin auto-login middleware
    ├── translations/
    │   └── en.yaml                                # English UI labels for Home Assistant options
    └── DOCS.md                                    # In-app Home Assistant documentation tab
```
