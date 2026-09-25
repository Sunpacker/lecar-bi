#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo "Usage: $0 BACKUP.sql.gz [postgres|notification-postgres] [database] [--compose-file FILE] [--project NAME] [--env-file FILE]" >&2
    exit 2
}

(($# >= 1)) || usage
BACKUP_FILE=$1
shift
TARGET_SERVICE=postgres
TARGET_DB=
if (($#)) && [[ "$1" != --* ]]; then TARGET_SERVICE=$1; shift; fi
if (($#)) && [[ "$1" != --* ]]; then TARGET_DB=$1; shift; fi
COMPOSE_FILE=${COMPOSE_FILE:-infra/docker-compose.yml}
COMPOSE_PROJECT=${COMPOSE_PROJECT:-}
INFRA_ENV_FILE=${INFRA_ENV_FILE:-$(test -f infra/.env && echo infra/.env || echo infra/.env.example)}
while (($#)); do
    case "$1" in
        --compose-file) (($# >= 2)) || usage; COMPOSE_FILE=$2; shift 2 ;;
        --project) (($# >= 2)) || usage; COMPOSE_PROJECT=$2; shift 2 ;;
        --env-file) (($# >= 2)) || usage; INFRA_ENV_FILE=$2; shift 2 ;;
        *) usage ;;
    esac
done

[[ "$TARGET_SERVICE" == postgres || "$TARGET_SERVICE" == notification-postgres ]] || usage
[[ -f "$BACKUP_FILE" && -f "$COMPOSE_FILE" && -f "$INFRA_ENV_FILE" ]] || { echo 'Backup, Compose or env file missing' >&2; exit 1; }
gzip -t "$BACKUP_FILE"
COMPOSE=(docker compose --env-file "$INFRA_ENV_FILE" -f "$COMPOSE_FILE")
if [[ -n "$COMPOSE_PROJECT" ]]; then COMPOSE+=(-p "$COMPOSE_PROJECT"); fi
if [[ -z "$TARGET_DB" ]]; then TARGET_DB=$("${COMPOSE[@]}" exec -T "$TARGET_SERVICE" printenv POSTGRES_DB); fi
[[ -n "$TARGET_DB" ]] || { echo 'Target database is empty' >&2; exit 1; }

echo "Restoring $BACKUP_FILE to $TARGET_SERVICE database $TARGET_DB"
gzip -dc "$BACKUP_FILE" | "${COMPOSE[@]}" exec -T "$TARGET_SERVICE" sh -ec 'psql -U "$POSTGRES_USER" -d "$1" --single-transaction --quiet --set ON_ERROR_STOP=1' sh "$TARGET_DB"
echo 'Restore complete'
