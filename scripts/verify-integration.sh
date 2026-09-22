#!/bin/sh

set -eu

FRONTEND_URL="${FRONTEND_URL:-http://127.0.0.1:3000}"
BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8080}"
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

backend_response="$(curl --fail --silent --show-error "$BACKEND_URL/api/v1/health")"
frontend_response="$(curl --fail --silent --show-error "$FRONTEND_URL/")"

printf '%s' "$backend_response" | grep --quiet '"status":"ok"'
printf '%s' "$backend_response" | grep --quiet '"service":"analytics"'
printf '%s' "$frontend_response" | grep --quiet 'Analytics API is ready'

echo "Integration check passed: web -> analytics health is available."
