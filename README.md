# WeatherNode Home Assistant Add-on Repository

A zero-maintenance, self-updating Home Assistant Add-on repository wrapping the official [WeatherNode](https://www.weathernode.dev/) container image (`ghcr.io/centauri/weathernode`).

[![Renovate enabled](https://img.shields.io/badge/renovate-enabled-brightgreen.svg)](https://renovatebot.com/)

---

## 🚀 Features

- **Native Home Assistant Integration**: Configured via the Home Assistant Web UI with built-in schema validation for options like `APP_URL`, `APP_KEY`, and logging drivers.
- **Persistent SQLite Backend**: Preconfigured with `DB_CONNECTION: sqlite` and persistent storage at `/data/weathernode.sqlite`, ensuring complete data retention across add-on restarts and upgrades.
- **Embedded Scheduler**: Runs Laravel task scheduling inside the add-on container (`DOCKER_RUN_SCHEDULER: true`) without requiring external cron jobs or secondary containers.
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
5. Generate an `APP_KEY` (e.g., `echo "base64:$(openssl rand -base64 32)"`), configure `APP_URL`, and start the add-on.

---

## 📂 Repository Structure

```
├── repository.json         # Home Assistant Add-on repository metadata
├── renovate.json           # Renovate bot configuration with custom regex manager & automerge
├── README.md               # Repository documentation
└── weathernode/
    ├── Dockerfile          # Wrapper Dockerfile extending ghcr.io/centauri/weathernode
    ├── config.yaml         # Home Assistant Add-on configuration & UI schema
    ├── run.sh              # Entrypoint script injecting /data/options.json into environment
    └── DOCS.md             # In-app Home Assistant documentation tab
```
