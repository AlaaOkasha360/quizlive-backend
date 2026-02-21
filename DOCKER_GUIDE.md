# Docker Deployment Guide — QuizLive Backend

This guide explains the Docker setup and how to maintain it as the project evolves.

---

## Architecture Overview

```
┌──────────────────────────────────────┐
│           Docker Container           │
│                                      │
│  ┌──────────┐   ┌────────────────┐   │
│  │  Nginx   │──▶│   PHP-FPM 8.2  │   │
│  │ (port $PORT) │   └────────────────┘   │
│  └──────────┘                        │
│                  ┌────────────────┐   │
│                  │ queue:work     │   │  ← optional
│                  │ (Supervisor)   │   │
│                  └────────────────┘   │
└──────────────────────────────────────┘
           │
           ▼
   External MySQL DB
```

Supervisor manages **three** processes: Nginx, PHP-FPM, and (optionally) the queue worker.

---

## File Structure

| File | Purpose |
|------|---------|
| `Dockerfile` | Multi-stage build (Composer install → production image) |
| `docker/nginx.conf` | Nginx site config; port is injected from `$PORT` at runtime |
| `docker/entrypoint.sh` | Caches config, runs migrations, starts Supervisor |
| `docker/php.ini` | PHP production tuning (memory, uploads, OPcache) |
| `docker/supervisord.conf` | Defines php-fpm, nginx, and queue-worker processes |
| `.dockerignore` | Keeps the build context small (excludes vendor, node_modules, .git, etc.) |

---

## Environment Variables (set in Render Dashboard)

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_NAME` | QuizLive | |
| `APP_ENV` | `production` | |
| `APP_KEY` | `base64:...` | Run `php artisan key:generate --show` locally |
| `APP_DEBUG` | `false` | **Never** `true` in production |
| `APP_URL` | `https://your-app.onrender.com` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | your MySQL host | From your free MySQL provider |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | your DB name | |
| `DB_USERNAME` | your DB user | |
| `DB_PASSWORD` | your DB password | |
| `SESSION_DRIVER` | `database` | or `cookie` if you prefer |
| `QUEUE_CONNECTION` | `database` | |
| `CACHE_STORE` | `database` | |
| `ENABLE_WORKER` | `true` | Set to `true` to start queue:work inside the container |

---

## Common Tasks

### 1. Adding a PHP Extension

Edit the `Dockerfile`, find the `docker-php-ext-install` line, and add your extension:

```diff
  && docker-php-ext-install -j$(nproc) \
      pdo_mysql \
      mbstring \
      bcmath \
      opcache \
      gd \
      zip \
      xml \
      pcntl \
+     intl \
```

If the extension needs a system library, add it to the `apt-get install` block above:

```diff
  RUN apt-get update && apt-get install -y --no-install-recommends \
+     libicu-dev \
      nginx \
```

Then push to GitHub → Render will rebuild automatically.

---

### 2. Adding a Composer Package

Run locally:

```bash
composer require some/package
```

Commit both `composer.json` and `composer.lock`, push to GitHub. The Dockerfile already runs `composer install` from the lock file.

---

### 3. Adding an NPM Package / Frontend Build

If you ever need a frontend build step, add a Node stage to the Dockerfile **before** the production stage:

```dockerfile
# Add after the composer-build stage
FROM node:20-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Then in the production stage, copy the built assets:
COPY --from=node-build /app/public/build /var/www/html/public/build
```

---

### 4. Enabling / Disabling the Queue Worker

**Option A — Inside the same container (simplest):**

Set the Render environment variable `ENABLE_WORKER=true`. Supervisor will auto-start `queue:work`.

**Option B — Dedicated Background Worker (recommended for heavy workloads):**

1. On Render, create a new **Background Worker** service
2. Point it to the **same** Docker image / repo
3. Override the start command to:
   ```
   php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
   ```
4. Set `ENABLE_WORKER=false` on the web service to avoid running the worker twice

---

### 5. Changing PHP Settings

Edit `docker/php.ini`. Common tweaks:

| Setting | Current | When to change |
|---------|---------|----------------|
| `memory_limit` | 256M | Large payloads or heavy processing |
| `upload_max_filesize` | 20M | File uploads |
| `post_max_size` | 25M | Should be slightly larger than upload_max_filesize |
| `max_execution_time` | 60 | Long-running API requests |
| `opcache.validate_timestamps` | 0 | Set to `1` during development |

---

### 6. Changing Nginx Settings

Edit `docker/nginx.conf`. Notable settings:

- `client_max_body_size 20M;` — must match your PHP upload limit
- The `${NGINX_PORT}` placeholder is replaced at runtime from Render's `PORT` env var

---

### 7. Adding a Cron / Scheduler

Add a new Supervisor program in `docker/supervisord.conf`:

```ini
[program:scheduler]
command=sh -c "while true; do php /var/www/html/artisan schedule:run --no-interaction; sleep 60; done"
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/www/html/storage/logs/scheduler.log
redirect_stderr=true
```

---

### 8. Debugging a Failed Deploy

1. Check the **Render deploy logs** — they show the Docker build output
2. If the container starts but crashes, check the **service logs** on Render
3. Common issues:
   - **Missing `APP_KEY`** → generate one: `php artisan key:generate --show`
   - **DB connection refused** → verify `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` in Render env vars
   - **Migration fails** → check if your MySQL provider allows the required operations

---

## Render-Specific Notes

- Render sets the `PORT` env var automatically — **do not hardcode** a port in Nginx
- Free-tier services spin down after inactivity; first request will be slow (cold start)
- Render auto-deploys on every push to the connected Git branch
- To skip a deploy, include `[skip render]` or `[render skip]` in your commit message
