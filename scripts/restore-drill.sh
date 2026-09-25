#!/usr/bin/env bash

set -euo pipefail

INFRA_ENV_FILE="${INFRA_ENV_FILE:-$(test -f infra/.env && echo infra/.env || echo infra/.env.example)}"
POSTGRES_USER="${POSTGRES_USER:-$(grep -E '^POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
POSTGRES_DB="${POSTGRES_DB:-$(grep -E '^POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo autobi)}"
NOTIFICATION_POSTGRES_USER="${NOTIFICATION_POSTGRES_USER:-$(grep -E '^NOTIFICATION_POSTGRES_USER=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"
NOTIFICATION_POSTGRES_DB="${NOTIFICATION_POSTGRES_DB:-$(grep -E '^NOTIFICATION_POSTGRES_DB=' "$INFRA_ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"'\'' ' || echo notification)}"

COMPOSE="docker compose --env-file $INFRA_ENV_FILE -f infra/docker-compose.yml"
DRILL_DIR="backups/drill_$(date +%Y%m%d_%H%M%S)"

mkdir -p "$DRILL_DIR"
START_TIME=$(date +%s)

echo "=========================================================="
echo "  Starting Disaster Recovery Restore Drill"
echo "  Date: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "=========================================================="

# 1. Back up Analytics PostgreSQL
ANALYTICS_DUMP="${DRILL_DIR}/analytics.sql.gz"
echo "Step 1: Creating live backup of Analytics DB..."
$COMPOSE exec -T postgres pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges | gzip > "$ANALYTICS_DUMP"

# 2. Back up Notification PostgreSQL
NOTIF_DUMP="${DRILL_DIR}/notification.sql.gz"
echo "Step 2: Creating live backup of Notification DB..."
$COMPOSE exec -T notification-postgres pg_dump -U "$NOTIFICATION_POSTGRES_USER" -d "$NOTIFICATION_POSTGRES_DB" --clean --if-exists --no-owner --no-privileges | gzip > "$NOTIF_DUMP"

# 3. Create isolated test database for Analytics
echo "Step 3: Creating isolated target database 'autobi_drill'..."
$COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS autobi_drill;" >/dev/null
$COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d postgres -c "CREATE DATABASE autobi_drill;" >/dev/null

# 4. Restore Analytics into isolated test database
echo "Step 4: Restoring Analytics backup into 'autobi_drill'..."
gzip -dc "$ANALYTICS_DUMP" | $COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d autobi_drill --single-transaction --quiet

# 5. Verify Analytics data parity
echo "Step 5: Verifying Analytics data integrity..."
ORIG_ORDERS=$($COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -t -A -c "SELECT count(*) FROM fact_orders;" || echo 0)
REST_ORDERS=$($COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d autobi_drill -t -A -c "SELECT count(*) FROM fact_orders;" || echo 0)

ORIG_WORKSPACES=$($COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -t -A -c "SELECT count(*) FROM workspaces;" || echo 0)
REST_WORKSPACES=$($COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d autobi_drill -t -A -c "SELECT count(*) FROM workspaces;" || echo 0)

if [ "$ORIG_ORDERS" != "$REST_ORDERS" ] || [ "$ORIG_WORKSPACES" != "$REST_WORKSPACES" ]; then
    echo "ERROR: Data mismatch between original and restored Analytics DB!" >&2
    echo "Orders: original=$ORIG_ORDERS, restored=$REST_ORDERS" >&2
    echo "Workspaces: original=$ORIG_WORKSPACES, restored=$REST_WORKSPACES" >&2
    exit 1
fi
echo "Analytics integrity verified: $REST_ORDERS orders, $REST_WORKSPACES workspaces."

# 6. Create isolated test database for Notification
echo "Step 6: Creating isolated target database 'notification_drill'..."
$COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS notification_drill;" >/dev/null
$COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d postgres -c "CREATE DATABASE notification_drill;" >/dev/null

# 7. Restore Notification into isolated test database
echo "Step 7: Restoring Notification backup into 'notification_drill'..."
gzip -dc "$NOTIF_DUMP" | $COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d notification_drill --single-transaction --quiet

# 8. Verify Notification data parity and deduplication consistency
echo "Step 8: Verifying Notification data integrity and deduplication..."
ORIG_NOTIFS=$($COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d "$NOTIFICATION_POSTGRES_DB" -t -A -c "SELECT count(*) FROM notifications;" || echo 0)
REST_NOTIFS=$($COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d notification_drill -t -A -c "SELECT count(*) FROM notifications;" || echo 0)

ORIG_EVENTS=$($COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d "$NOTIFICATION_POSTGRES_DB" -t -A -c "SELECT count(*) FROM consumed_events;" || echo 0)
REST_EVENTS=$($COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d notification_drill -t -A -c "SELECT count(*) FROM consumed_events;" || echo 0)

if [ "$ORIG_NOTIFS" != "$REST_NOTIFS" ] || [ "$ORIG_EVENTS" != "$REST_EVENTS" ]; then
    echo "ERROR: Data mismatch between original and restored Notification DB!" >&2
    echo "Notifications: original=$ORIG_NOTIFS, restored=$REST_NOTIFS" >&2
    echo "Consumed events: original=$ORIG_EVENTS, restored=$REST_EVENTS" >&2
    exit 1
fi
echo "Notification integrity verified: $REST_NOTIFS notifications, $REST_EVENTS consumed events."

# 9. Cleanup isolated test databases
echo "Step 9: Cleaning up drill databases..."
$COMPOSE exec -T postgres psql -U "$POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS autobi_drill;" >/dev/null
$COMPOSE exec -T notification-postgres psql -U "$NOTIFICATION_POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS notification_drill;" >/dev/null

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo "=========================================================="
echo "  Disaster Recovery Drill Completed Successfully!"
echo "  Recovery Time Actual (RTO): ${DURATION} seconds (Target: < 900s)"
echo "  Data Loss Actual (RPO): 0 records (Target: < 3600s)"
echo "  Artifacts: ${DRILL_DIR}"
echo "=========================================================="
