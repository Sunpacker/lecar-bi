#!/usr/bin/env bash

set -euo pipefail

TARGET="${1:-all}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
INFRA_ENV_FILE="${INFRA_ENV_FILE:-$(test -f infra/.env && echo infra/.env || echo infra/.env.example)}"
POSTGRES_USER="${POSTGRES_USER:-$(grep -E '^POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
POSTGRES_DB="${POSTGRES_DB:-$(grep -E '^POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
NOTIFICATION_POSTGRES_USER="${NOTIFICATION_POSTGRES_USER:-$(grep -E '^NOTIFICATION_POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"
NOTIFICATION_POSTGRES_DB="${NOTIFICATION_POSTGRES_DB:-$(grep -E '^NOTIFICATION_POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"

mkdir -p "$BACKUP_DIR"

backup_analytics() {
    local outfile="${BACKUP_DIR}/analytics_${TIMESTAMP}.sql.gz"
    echo "Creating backup of Analytics PostgreSQL database to ${outfile}..."
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T postgres \
        pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges | gzip > "$outfile"
    echo "Analytics backup complete: ${outfile} ($(du -h "$outfile" | cut -f1))"
}

backup_notification() {
    local outfile="${BACKUP_DIR}/notification_${TIMESTAMP}.sql.gz"
    echo "Creating backup of Notification PostgreSQL database to ${outfile}..."
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification-postgres \
        pg_dump -U "$NOTIFICATION_POSTGRES_USER" -d "$NOTIFICATION_POSTGRES_DB" --clean --if-exists --no-owner --no-privileges | gzip > "$outfile"
    echo "Notification backup complete: ${outfile} ($(du -h "$outfile" | cut -f1))"
}

cleanup_old_backups() {
    echo "Cleaning up backups older than ${RETENTION_DAYS} days in ${BACKUP_DIR}..."
    find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +"$RETENTION_DAYS" -delete || true
}

case "$TARGET" in
    analytics)
        backup_analytics
        ;;
    notification)
        backup_notification
        ;;
    all)
        backup_analytics
        backup_notification
        ;;
    *)
        echo "Usage: $0 [analytics|notification|all]" >&2
        exit 1
        ;;
esac

cleanup_old_backups
echo "Backup operations finished successfully."
