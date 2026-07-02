#!/usr/bin/env bash
###############################################################################
# ColdChain EMS — container entrypoint
#
# Shared by every PHP container (api, worker, scheduler, mqtt). The service
# ROLE is passed as CONTAINER_ROLE so a single image can boot php-fpm, a queue
# worker, the scheduler, or the MQTT subscriber.
#
# On the *api* role we run one-time bootstrap (wait for DB, migrate, cache
# config) before handing control to php-fpm. Workers/scheduler skip migrations
# (the api container is the single migrator) but still wait for dependencies.
###############################################################################
set -euo pipefail

ROLE="${CONTAINER_ROLE:-app}"
APP_DIR="/var/www/html"
cd "$APP_DIR"

log() { printf '\033[0;36m[entrypoint:%s]\033[0m %s\n' "$ROLE" "$*"; }

# ---------------------------------------------------------------------------
# Ensure an application key + JWT secret exist. In real deployments these come
# from the environment/secrets manager; for local dev we generate on first boot
# if the developer forgot to seed them.
# ---------------------------------------------------------------------------
ensure_app_key() {
    if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
        log "APP_KEY missing — generating"
        php artisan key:generate --force --no-interaction || true
    fi
    if [ -z "${JWT_SECRET:-}" ] && ! grep -q '^JWT_SECRET=.\+' .env 2>/dev/null; then
        log "JWT_SECRET missing — generating"
        php artisan jwt:secret --force --no-interaction || true
    fi
}

# ---------------------------------------------------------------------------
# Block until TCP dependencies (MySQL, Redis) accept connections. Prevents the
# classic "SQLSTATE[HY000] [2002] Connection refused" race on cold `up`.
# ---------------------------------------------------------------------------
wait_for() {
    local host="$1" port="$2" name="$3" tries="${4:-60}"
    log "waiting for ${name} (${host}:${port})"
    for _ in $(seq 1 "$tries"); do
        if (echo > "/dev/tcp/${host}/${port}") >/dev/null 2>&1; then
            log "${name} is up"
            return 0
        fi
        sleep 1
    done
    log "ERROR: ${name} did not become ready in time"
    return 1
}

wait_for_dependencies() {
    [ -n "${DB_HOST:-}" ]    && wait_for "${DB_HOST}"    "${DB_PORT:-3306}" "mysql" || true
    [ -n "${REDIS_HOST:-}" ] && wait_for "${REDIS_HOST}" "${REDIS_PORT:-6379}" "redis" || true
}

bootstrap_app() {
    ensure_app_key
    wait_for_dependencies

    log "running database migrations"
    php artisan migrate --force --no-interaction

    if [ "${APP_ENV:-local}" != "local" ]; then
        log "caching config/routes/events for production"
        php artisan config:cache
        php artisan route:cache
        php artisan event:cache
    else
        # Clear any stale cached config baked into the image layer.
        php artisan config:clear || true
    fi

    php artisan storage:link || true
}

case "$ROLE" in
    app|api|php-fpm)
        bootstrap_app
        log "starting php-fpm"
        exec php-fpm
        ;;
    worker)
        wait_for_dependencies
        log "starting queue worker"
        # Long-running; --max-time recycles the process hourly to avoid leaks.
        exec php artisan queue:work redis \
            --queue="${QUEUE_NAMES:-billing,notifications,telemetry,reports,default}" \
            --sleep=1 --tries=3 --backoff=5 --max-time=3600 --max-jobs=1000
        ;;
    scheduler)
        wait_for_dependencies
        log "starting scheduler loop"
        exec php artisan schedule:work
        ;;
    mqtt)
        wait_for_dependencies
        log "starting MQTT ingest subscriber"
        exec php artisan mqtt:listen
        ;;
    horizon)
        wait_for_dependencies
        log "starting Horizon"
        exec php artisan horizon
        ;;
    *)
        # Fall through to an arbitrary command (e.g. `artisan tinker`, tests).
        log "exec custom command: $*"
        exec "$@"
        ;;
esac
