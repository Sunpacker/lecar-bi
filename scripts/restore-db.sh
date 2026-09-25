#!/usr/bin/env bash

set -euo pipefail

BACKUP_FILE="${1:?Usage: $0 <backup-file.sql.gz> [target-db-service: postgres|notification-postgres] [target-database-name]}"
TARGET_SERVICE="${2:-postgres}"
INFRA_ENV_FILE="${INFRA_ENV_FILE:-$(test -f infra/.env && echo infra/.env || echo infra/.env.example)}"
POSTGRES_USER="${POSTGRES_USER:-$(grep -E '^POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
POSTGRES_DB="${POSTGRES_DB:-$(grep -E '^POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
NOTIFICATION_POSTGRES_USER="${NOTIFICATION_POSTGRES_USER:-$(grep -E '^NOTIFICATION_POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"
NOTIFICATION_POSTGRES_DB="${NOTIFICATION_POSTGRES_DB:-$(grep -E '^NOTIFICATION_POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"

TARGET_DB="${3:-$([ "$TARGET_SERVICE" = "postgres" ] && echo "$POSTGRES_DB" || echo "$NOTIFICATION_POSTGRES_DB")}"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Error: Backup file $BACKUP_FILE does not exist" >&2
    exit 1
fi

echo "Restoring $BACKUP_FILE to service '$TARGET_SERVICE', database '$TARGET_DB'..."

if [ "$TARGET_SERVICE" = "postgres" ]; then
    DB_USER="$POSTGRES_USER"
else
    DB_USER="$NOTIFICATION_POSTGRES_USER"
fi

gzip -dc "$BACKUP_FILE" | docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T "$TARGET_SERVICE" \
    psql -U "$DB_USER" -d "$TARGET_DB" --single-transaction --quiet

echo "Restore into '$TARGET_DB' completed successfully."
