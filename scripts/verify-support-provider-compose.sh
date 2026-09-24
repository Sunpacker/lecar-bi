#!/bin/sh

set -eu

if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required to verify support provider Compose configuration" >&2
    exit 1
fi

compose_config() {
    environment_name="$1"
    shift

    env -u GEMMA_API_KEY \
        APP_ENV=local \
        APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        SESSION_SECRET=compose-test-session-secret-at-least-32 \
        SUPPORT_BFF_SHARED_SECRET=compose-test-bff-secret-at-least-32 \
        POSTGRES_PASSWORD=compose-test-postgres-password \
        NOTIFICATION_APP_KEY=base64:BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB= \
        NOTIFICATION_POSTGRES_PASSWORD=compose-test-notification-password \
        SUPPORT_AI_ADAPTER=google \
        "$@" \
        docker compose --project-name "support-provider-${environment_name}" \
            --file infra/docker-compose.yml \
            --file "infra/docker-compose.${environment_name}.yml" \
            config --format json
}

assert_dev_provider_is_not_masked() {
    config="$1"

    for service in backend support-generation-worker support-indexing-worker; do
        if printf '%s' "$config" | jq --exit-status --arg service "$service" '
            .services[$service].environment.SUPPORT_AI_ADAPTER == "google"
            and (.services[$service].environment | has("GEMMA_API_KEY") | not)
        ' >/dev/null; then continue; fi

        echo "Dev Compose masks backend/.env provider configuration for $service" >&2
        exit 1
    done
}

assert_vps_provider_is_explicit() {
    config="$1"

    for service in backend support-generation-worker support-indexing-worker; do
        if printf '%s' "$config" | jq --exit-status --arg service "$service" '
            .services[$service].environment.SUPPORT_AI_ADAPTER == "google"
            and .services[$service].environment.GEMMA_API_KEY == "compose-test-google-key"
        ' >/dev/null; then continue; fi

        echo "VPS Compose does not pass explicit provider configuration to $service" >&2
        exit 1
    done
}

dev_config="$(compose_config dev)"
vps_config="$(compose_config vps GEMMA_API_KEY=compose-test-google-key)"

assert_dev_provider_is_not_masked "$dev_config"
assert_vps_provider_is_explicit "$vps_config"

echo "Support provider Compose configuration is valid"
