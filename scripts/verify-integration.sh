#!/bin/sh

set -eu

FRONTEND_URL="${FRONTEND_URL:-http://127.0.0.1:3000}"
BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8080}"
INFRA_ENV_FILE="${INFRA_ENV_FILE:-infra/.env.example}"
MAX_ATTEMPTS="${MAX_ATTEMPTS:-30}"

wait_for_service() {
    service_name="$1"
    service_url="$2"
    attempt=1

    while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
        if curl --fail --silent --show-error "$service_url" >/dev/null; then
            return 0
        fi

        attempt=$((attempt + 1))
        sleep 2
    done

    echo "$service_name did not become ready at $service_url" >&2
    return 1
}

wait_for_service "analytics" "$BACKEND_URL/api/v1/health"
wait_for_service "web" "$FRONTEND_URL/api/health"

# Apply migrations and seed data if docker compose environment is active
if command -v docker >/dev/null 2>&1; then
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php artisan migrate --force --seed >/dev/null 2>&1 || true
fi

# 1. Technical health check
backend_response="$(curl --fail --silent --show-error "$BACKEND_URL/api/v1/health")"
printf '%s' "$backend_response" | grep --quiet '"status":"ok"'
printf '%s' "$backend_response" | grep --quiet '"service":"analytics"'

# 2. Access boundary: unauthenticated request returns 401
unauth_status="$(curl --silent -o /dev/null -w "%{http_code}" "$BACKEND_URL/api/v1/workspaces")"
if [ "$unauth_status" != "401" ]; then
    echo "Expected 401 for unauthenticated request to /workspaces, got $unauth_status" >&2
    exit 1
fi

# 3. Access boundary: user-1 can only list accessible workspaces
user1_workspaces="$(curl --fail --silent --show-error -H "X-User-Id: user-1" "$BACKEND_URL/api/v1/workspaces")"
printf '%s' "$user1_workspaces" | grep --quiet '"id":"ws-1"'

# 4. Cross-workspace isolation: user-1 attempting to access user-2's workspace returns 403 Forbidden
cross_access_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" "$BACKEND_URL/api/v1/workspaces/ws-2")"
if [ "$cross_access_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to /workspaces/ws-2, got $cross_access_status" >&2
    exit 1
fi

# 5. Frontend UI renders
frontend_response="$(curl --fail --silent --show-error "$FRONTEND_URL/")"
printf '%s' "$frontend_response" | grep --quiet 'Analytics workspace'

echo "Integration check passed: web -> analytics health, identity, and workspace access boundaries are verified."
