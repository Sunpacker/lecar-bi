#!/usr/bin/env bash
set -euo pipefail
umask 077

TARGET=all
BACKUP_DIR=${BACKUP_DIR:-backups}
RETENTION_DAYS=${RETENTION_DAYS:-7}
COMPOSE_FILE=${COMPOSE_FILE:-infra/docker-compose.yml}
COMPOSE_PROJECT=${COMPOSE_PROJECT:-}
INFRA_ENV_FILE=${INFRA_ENV_FILE:-$(test -f infra/.env && echo infra/.env || echo infra/.env.example)}
TEMP_FILE=

usage() {
    echo "Usage: $0 [analytics|notification|all] [--compose-file FILE] [--project NAME] [--env-file FILE] [--backup-dir DIR]" >&2
    exit 2
}

while (($#)); do
    case "$1" in
        analytics|notification|all) TARGET=$1; shift ;;
        --compose-file) (($# >= 2)) || usage; COMPOSE_FILE=$2; shift 2 ;;
        --project) (($# >= 2)) || usage; COMPOSE_PROJECT=$2; shift 2 ;;
        --env-file) (($# >= 2)) || usage; INFRA_ENV_FILE=$2; shift 2 ;;
        --backup-dir) (($# >= 2)) || usage; BACKUP_DIR=$2; shift 2 ;;
        *) usage ;;
    esac
done

[[ -f "$COMPOSE_FILE" && -f "$INFRA_ENV_FILE" ]] || { echo 'Compose or env file missing' >&2; exit 1; }
[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] || { echo 'RETENTION_DAYS must be a nonnegative integer' >&2; exit 1; }
mkdir -p "$BACKUP_DIR"
BACKUP_DIR=$(cd "$BACKUP_DIR" && pwd -P)
COMPOSE_FILE=$(realpath "$COMPOSE_FILE")
INFRA_ENV_FILE=$(realpath "$INFRA_ENV_FILE")
COMPOSE=(docker compose --env-file "$INFRA_ENV_FILE" -f "$COMPOSE_FILE")
if [[ -n "$COMPOSE_PROJECT" ]]; then COMPOSE+=(-p "$COMPOSE_PROJECT"); fi

cleanup_temp() {
    if [[ -n "$TEMP_FILE" ]]; then rm -f -- "$TEMP_FILE"; fi
}
trap cleanup_temp EXIT

backup_database() {
    local service=$1
    local label=$2
    local timestamp=$3
    local destination="$BACKUP_DIR/${label}_${timestamp}.sql.gz"
    TEMP_FILE=$(mktemp "$BACKUP_DIR/.${label}.XXXXXX")
    echo "Backing up $service to $destination"
    "${COMPOSE[@]}" exec -T "$service" sh -ec 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges' | gzip -c > "$TEMP_FILE"
    gzip -t "$TEMP_FILE"
    [[ -s "$TEMP_FILE" ]] || { echo "Empty backup: $service" >&2; exit 1; }
    mv -- "$TEMP_FILE" "$destination"
    TEMP_FILE=
}

TIMESTAMP=$(date -u +%Y%m%d_%H%M%S)
if [[ "$TARGET" == all || "$TARGET" == analytics ]]; then backup_database postgres analytics "$TIMESTAMP"; fi
if [[ "$TARGET" == all || "$TARGET" == notification ]]; then backup_database notification-postgres notification "$TIMESTAMP"; fi
if ((RETENTION_DAYS > 0)); then
    find "$BACKUP_DIR" -maxdepth 1 -type f -name '*.sql.gz' -mtime +"$RETENTION_DAYS" -delete
fi
echo "Backups complete: $BACKUP_DIR"
