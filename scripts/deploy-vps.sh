#!/usr/bin/env bash
set -euo pipefail
umask 077

usage() {
    echo "Usage: $0 --sha FULL_SHA --release-dir ABS_PATH --project NAME --env-file ABS_PATH --backup-dir ABS_PATH --repository OWNER/REPO" >&2
    exit 2
}

while (($#)); do
    case "$1" in
        --sha) RELEASE_SHA=${2:-}; shift 2 ;;
        --release-dir) RELEASE_DIR=${2:-}; shift 2 ;;
        --project) COMPOSE_PROJECT=${2:-}; shift 2 ;;
        --env-file) ENV_FILE=${2:-}; shift 2 ;;
        --backup-dir) BACKUP_DIR=${2:-}; shift 2 ;;
        --repository) REPOSITORY=${2:-}; shift 2 ;;
        *) usage ;;
    esac
done

[[ ${RELEASE_SHA:-} =~ ^[a-f0-9]{40}$ ]] || usage
[[ ${RELEASE_DIR:-} == /* && ${ENV_FILE:-} == /* && ${BACKUP_DIR:-} == /* ]] || usage
[[ ${COMPOSE_PROJECT:-} =~ ^[a-zA-Z0-9][a-zA-Z0-9_-]*$ ]] || usage
[[ ${REPOSITORY:-} =~ ^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$ ]] || usage
[[ -f "$ENV_FILE" && -f "$RELEASE_DIR/infra/docker-compose.vps.yml" && -d "$RELEASE_DIR/infra/observability" ]] || { echo 'Release package or server env file missing' >&2; exit 1; }
RELEASE_DIR=$(realpath "$RELEASE_DIR")
ENV_FILE=$(realpath "$ENV_FILE")
mkdir -p "$BACKUP_DIR"
BACKUP_DIR=$(realpath "$BACKUP_DIR")
RELEASES_DIR=$(dirname "$RELEASE_DIR")
[[ $(basename "$RELEASE_DIR") == "$RELEASE_SHA" ]] || { echo 'Release directory must end in the release SHA' >&2; exit 1; }
[[ "$ENV_FILE" != "$RELEASES_DIR/"* && "$BACKUP_DIR" != "$RELEASES_DIR/"* ]] || { echo 'Env and backups must be outside releases' >&2; exit 1; }

REPOSITORY=${REPOSITORY,,}
BACKEND_IMAGE="ghcr.io/$REPOSITORY/backend:sha-$RELEASE_SHA"
NOTIFICATION_IMAGE="ghcr.io/$REPOSITORY/notification:sha-$RELEASE_SHA"
BACKEND_TAG=$BACKEND_IMAGE
NOTIFICATION_TAG=$NOTIFICATION_IMAGE
COMPOSE_FILE="$RELEASE_DIR/infra/docker-compose.vps.yml"
STATE_DIR="$(dirname "$RELEASES_DIR")/deploy-state"
mkdir -p "$STATE_DIR"
exec 9>"$STATE_DIR/deploy.lock"
flock 9
COMPOSE=(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$COMPOSE_PROJECT")

compose() {
    BACKEND_IMAGE=$BACKEND_IMAGE NOTIFICATION_IMAGE=$NOTIFICATION_IMAGE "${COMPOSE[@]}" "$@"
}

container_id() {
    compose ps -q "$1"
}

require_running_service() {
    local service=$1
    local id
    id=$(container_id "$service")
    [[ -n "$id" ]] || { echo "Missing running service: $service" >&2; exit 1; }
    [[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$id") == "$COMPOSE_PROJECT" ]] || { echo "Compose project mismatch: $service" >&2; exit 1; }
}

check_volumes() {
    local volume
    for volume in postgres-data notification-postgres-data redis-data prometheus-data loki-data grafana-data; do
        docker volume inspect "${COMPOSE_PROJECT}_${volume}" >/dev/null
    done
}

check_config() {
    docker info >/dev/null
    docker network inspect "$(compose config --format json | python3 -c 'import json,sys; print(json.load(sys.stdin)["networks"]["proxy"]["name"])')" >/dev/null
    compose config -q
    check_volumes
    for service in postgres notification-postgres redis backend notification notification-worker; do require_running_service "$service"; done
}

capture_previous_images() {
    local backend_id notification_id worker_id
    backend_id=$(docker inspect -f '{{.Image}}' "$(container_id backend)")
    notification_id=$(docker inspect -f '{{.Image}}' "$(container_id notification)")
    worker_id=$(docker inspect -f '{{.Image}}' "$(container_id notification-worker)")
    [[ "$notification_id" == "$worker_id" ]] || { echo 'Notification and worker image IDs differ' >&2; exit 1; }
    PREVIOUS_BACKEND_IMAGE=$backend_id
    PREVIOUS_NOTIFICATION_IMAGE=$notification_id
}

pull_release() {
    docker pull "$BACKEND_TAG"
    docker pull "$NOTIFICATION_TAG"
    BACKEND_DIGEST=$(docker image inspect "$BACKEND_TAG" --format '{{range .RepoDigests}}{{println .}}{{end}}' | awk -v prefix="ghcr.io/$REPOSITORY/backend@sha256:" 'index($0,prefix)==1 { print; exit }')
    NOTIFICATION_DIGEST=$(docker image inspect "$NOTIFICATION_TAG" --format '{{range .RepoDigests}}{{println .}}{{end}}' | awk -v prefix="ghcr.io/$REPOSITORY/notification@sha256:" 'index($0,prefix)==1 { print; exit }')
    [[ "$BACKEND_DIGEST" == "ghcr.io/$REPOSITORY/backend@sha256:"* ]] || { echo 'Backend digest mismatch' >&2; exit 1; }
    [[ "$NOTIFICATION_DIGEST" == "ghcr.io/$REPOSITORY/notification@sha256:"* ]] || { echo 'Notification digest mismatch' >&2; exit 1; }
    BACKEND_IMAGE=$BACKEND_DIGEST
    NOTIFICATION_IMAGE=$NOTIFICATION_DIGEST
}

wait_for_health() {
    local deadline=$((SECONDS + 180))
    local service id status
    while ((SECONDS < deadline)); do
        local ready=1
        for service in backend notification notification-worker; do
            id=$(container_id "$service")
            if [[ -z "$id" ]]; then ready=0; break; fi
            status=$(docker inspect -f '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}' "$id")
            if [[ "$status" != 'running healthy' ]]; then ready=0; break; fi
        done
        if ((ready)); then return; fi
        sleep 5
    done
    echo 'Application health timeout' >&2
    return 1
}

check_worker_heartbeat() {
    local id started heartbeat
    id=$(container_id notification-worker)
    started=$(date -d "$(docker inspect -f '{{.State.StartedAt}}' "$id")" +%s)
    heartbeat=$(compose exec -T redis redis-cli -n "$REDIS_DB" --raw GET autobi:worker:notification-worker:heartbeat)
    HEARTBEAT_JSON="$heartbeat" STARTED_AT="$started" python3 -c 'import json,os,sys; data=json.loads(os.environ["HEARTBEAT_JSON"]); sys.exit(0 if data.get("status") == "running" and int(data.get("heartbeat_ts",0)) >= int(os.environ["STARTED_AT"]) else 1)'
}

check_restart_counts() {
    local service id
    for service in backend notification notification-worker; do
        id=$(container_id "$service")
        [[ $(docker inspect -f '{{.RestartCount}}' "$id") == 0 ]] || { echo "Restart detected: $service" >&2; return 1; }
    done
}

check_running_images() {
    local service expected actual
    for service in backend notification notification-worker; do
        if [[ "$service" == backend ]]; then
            expected=$(docker image inspect "$BACKEND_IMAGE" --format '{{.Id}}') || return 1
        else
            expected=$(docker image inspect "$NOTIFICATION_IMAGE" --format '{{.Id}}') || return 1
        fi
        actual=$(docker inspect -f '{{.Image}}' "$(container_id "$service")") || return 1
        [[ "$actual" == "$expected" ]] || { echo "Unexpected image: $service" >&2; return 1; }
    done
}

verify_release() {
    wait_for_health || return 1
    check_running_images || return 1
    curl --fail --show-error --silent --max-time 10 https://api.veloza.ru/lecar-bi/api/v1/health >/dev/null || return 1
    compose exec -T notification curl --fail --show-error --silent http://127.0.0.1:8081/api/v1/health/ready >/dev/null || return 1
    check_worker_heartbeat || return 1
    sleep 10
    check_restart_counts || return 1
}

rollback() {
    echo 'Deploy failed; restoring previous application images' >&2
    local diagnostics="$STATE_DIR/failure-${RELEASE_SHA}-$(date -u +%Y%m%d_%H%M%S).log"
    compose logs --no-color --tail=100 backend notification notification-worker > "$diagnostics" 2>&1 || true
    echo "Application diagnostics saved on VPS: $diagnostics" >&2
    BACKEND_IMAGE=$PREVIOUS_BACKEND_IMAGE
    NOTIFICATION_IMAGE=$PREVIOUS_NOTIFICATION_IMAGE
    if ! compose up -d --no-deps --force-recreate backend notification notification-worker; then
        echo 'Automatic application rollback failed; operator recovery required' >&2
        return 1
    fi
    if ! verify_release; then
        echo 'Previous application images did not pass health checks; operator recovery required' >&2
        return 1
    fi
    echo 'Previous application images restored; database schema was not rolled back' >&2
}

save_release() {
    local state_file="$STATE_DIR/current.tmp"
    printf 'sha=%s\nbackend_tag=%s\nbackend_image=%s\nnotification_tag=%s\nnotification_image=%s\nrelease_dir=%s\n' \
        "$RELEASE_SHA" "$BACKEND_TAG" "$BACKEND_DIGEST" "$NOTIFICATION_TAG" "$NOTIFICATION_DIGEST" "$RELEASE_DIR" > "$state_file"
    if [[ -f "$STATE_DIR/current" ]]; then cp "$STATE_DIR/current" "$STATE_DIR/previous"; fi
    mv -f "$state_file" "$STATE_DIR/current"
}

check_config
REDIS_DB=$(compose exec -T notification-worker printenv INTEGRATION_EVENTS_REDIS_DB)
[[ "$REDIS_DB" =~ ^[0-9]+$ ]] || { echo 'Invalid integration Redis DB' >&2; exit 1; }

if [[ -f "$STATE_DIR/current" ]] && [[ $(sed -n 's/^sha=//p' "$STATE_DIR/current") == "$RELEASE_SHA" ]]; then
    echo "Release $RELEASE_SHA already recorded; verifying services"
    verify_release
    exit 0
fi

capture_previous_images
pull_release
RETENTION_DAYS=0 "$RELEASE_DIR/scripts/backup-db.sh" all --compose-file "$COMPOSE_FILE" --project "$COMPOSE_PROJECT" --env-file "$ENV_FILE" --backup-dir "$BACKUP_DIR"
if [[ ! -f "$STATE_DIR/current" ]]; then
    printf 'backend_image=%s\nnotification_image=%s\n' "$PREVIOUS_BACKEND_IMAGE" "$PREVIOUS_NOTIFICATION_IMAGE" > "$STATE_DIR/baseline"
fi

DEPLOY_STARTED=1
on_failure() {
    local result=$?
    trap - ERR
    if [[ ${DEPLOY_STARTED:-0} == 1 ]]; then rollback || true; fi
    exit "$result"
}
trap on_failure ERR

compose stop backend notification notification-worker
compose exec -T redis redis-cli -n "$REDIS_DB" DEL autobi:worker:notification-worker:heartbeat >/dev/null
compose run --rm --no-deps backend php artisan migrate --force
compose run --rm --no-deps notification php artisan migrate --force
compose up -d --no-deps --force-recreate backend notification notification-worker
verify_release
save_release
trap - ERR
echo "Release $RELEASE_SHA deployed; backend=$BACKEND_DIGEST notification=$NOTIFICATION_DIGEST"
