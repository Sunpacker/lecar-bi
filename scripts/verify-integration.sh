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

# 4b. Backend auth login endpoint verification
login_response="$(curl --fail --silent --show-error -H "Content-Type: application/json" -d '{"email":"elena@autobi.internal","password":"password123"}' "$BACKEND_URL/api/v1/auth/login")"
printf '%s' "$login_response" | grep --quiet '"id":"user-1"'

# 5. Frontend Auth & UI: unauthenticated request redirects to /login
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

unauth_frontend_status="$(curl --silent -o /dev/null -w "%{http_code}" "$FRONTEND_URL/")"
if [ "$unauth_frontend_status" != "307" ] && [ "$unauth_frontend_status" != "302" ]; then
    echo "Expected 307 or 302 redirect for unauthenticated request to /, got $unauth_frontend_status" >&2
    exit 1
fi

login_page_response="$(curl --fail --silent --show-error "$FRONTEND_URL/login")"
printf '%s' "$login_page_response" | grep --quiet 'Вход в систему'

# Authenticate via Next.js BFF and save session cookie
curl --fail --silent --show-error -c "$COOKIE_JAR" -H "Content-Type: application/json" \
    -d '{"email":"elena@autobi.internal","password":"password123"}' \
    "$FRONTEND_URL/api/auth/login" >/dev/null

# Authenticated frontend request renders workspace
frontend_response="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/")"
printf '%s' "$frontend_response" | grep --quiet 'Analytics workspace'

# 6. Verify analytics demo dataset generation in PostgreSQL
if command -v docker >/dev/null 2>&1; then
    echo "Verifying PostgreSQL demo dataset..."
    ws1_orders_count="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \Illuminate\Support\Facades\DB::table("fact_orders")->where("workspace_id", "ws-1")->count();' 2>/dev/null | tr -d '\r\n')"
    if [ -z "$ws1_orders_count" ] || [ "$ws1_orders_count" -lt 1000 ]; then
        echo "Expected at least 1000 orders for ws-1, got: $ws1_orders_count" >&2
        exit 1
    fi

    # Verify inventory snapshots and stockouts exist
    stockout_count="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \Illuminate\Support\Facades\DB::table("fact_inventory_daily")->where("workspace_id", "ws-1")->where("quantity_available", "<=", 0)->count();' 2>/dev/null | tr -d '\r\n')"
    if [ -z "$stockout_count" ] || [ "$stockout_count" -lt 1 ]; then
        echo "Expected at least one stockout in inventory snapshots, got: $stockout_count" >&2
        exit 1
    fi

    # Verify workspace 2 is also populated and isolated
    ws2_orders_count="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \Illuminate\Support\Facades\DB::table("fact_orders")->where("workspace_id", "ws-2")->count();' 2>/dev/null | tr -d '\r\n')"
    if [ -z "$ws2_orders_count" ] || [ "$ws2_orders_count" -lt 1000 ]; then
        echo "Expected at least 1000 orders for ws-2, got: $ws2_orders_count" >&2
        exit 1
    fi

    echo "Demo dataset verified: ws-1 ($ws1_orders_count orders), ws-2 ($ws2_orders_count orders), stockouts ($stockout_count days) present in PostgreSQL."
fi

# 7. Sales filters endpoint
sales_filters="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/filters")"
printf '%s' "$sales_filters" | grep --quiet '"categories":\['
printf '%s' "$sales_filters" | grep --quiet '"regions":\['

# 8. Sales overview endpoint returns aggregated summary from PostgreSQL
sales_overview="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/overview")"
printf '%s' "$sales_overview" | grep --quiet '"total_revenue":'
printf '%s' "$sales_overview" | grep --quiet '"order_count":'
printf '%s' "$sales_overview" | grep --quiet '"trend":\['
printf '%s' "$sales_overview" | grep --quiet '"categories":\['
printf '%s' "$sales_overview" | grep --quiet '"regions":\['

# 9. Cross-workspace sales analytics isolation: user-1 accessing ws-2 returns 403
sales_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/sales/overview")"
if [ "$sales_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace sales analytics access, got $sales_cross_status" >&2
    exit 1
fi

# 10. Sales detail records endpoint returns paginated items from PostgreSQL
sales_records="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc")"
printf '%s' "$sales_records" | grep --quiet '"items":\['
printf '%s' "$sales_records" | grep --quiet '"pagination":{'
printf '%s' "$sales_records" | grep --quiet '"total":'

# 11. Cross-workspace sales records isolation: user-1 accessing ws-2 records returns 403
sales_records_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/sales/records")"
if [ "$sales_records_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace sales records access, got $sales_records_cross_status" >&2
    exit 1
fi

# 12. Frontend UI renders Sales Analytics Dashboard and Detail Table
frontend_dashboard="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/")"
printf '%s' "$frontend_dashboard" | grep --quiet 'Аналитика продаж'

# 13. Inventory filters endpoint
inventory_filters="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/filters")"
printf '%s' "$inventory_filters" | grep --quiet '"warehouses":\['
printf '%s' "$inventory_filters" | grep --quiet '"statuses":\['

# 14. Inventory summary endpoint returns aggregated metrics
inventory_summary="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/summary")"
printf '%s' "$inventory_summary" | grep --quiet '"total_items":'
printf '%s' "$inventory_summary" | grep --quiet '"health_breakdown":\['
printf '%s' "$inventory_summary" | grep --quiet '"warehouses":\['

# 15. Inventory items endpoint returns paginated items with health status
inventory_items="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/items?page=1&per_page=10")"
printf '%s' "$inventory_items" | grep --quiet '"items":\['
printf '%s' "$inventory_items" | grep --quiet '"pagination":{'
printf '%s' "$inventory_items" | grep --quiet '"stock_health":'

# 16. Cross-workspace inventory isolation: user-1 accessing ws-2 returns 403
inventory_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/inventory/summary")"
if [ "$inventory_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace inventory summary access, got $inventory_cross_status" >&2
    exit 1
fi

# 17. Frontend UI renders Inventory Dashboard
inventory_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/inventory")"
printf '%s' "$inventory_page" | grep --quiet 'Управление запасами'

# 18. Inventory ABC/XYZ summary endpoint returns 3x3 matrix and distributions
abc_xyz_summary="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/summary?period_days=90")"
printf '%s' "$abc_xyz_summary" | grep --quiet '"matrix":\['
printf '%s' "$abc_xyz_summary" | grep --quiet '"abc_distribution":\['
printf '%s' "$abc_xyz_summary" | grep --quiet '"xyz_distribution":\['

# 19. Inventory ABC/XYZ items endpoint returns classified catalog
abc_xyz_items="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/items?period_days=90&page=1&per_page=10")"
printf '%s' "$abc_xyz_items" | grep --quiet '"items":\['
printf '%s' "$abc_xyz_items" | grep --quiet '"abc_class":'
printf '%s' "$abc_xyz_items" | grep --quiet '"xyz_class":'
printf '%s' "$abc_xyz_items" | grep --quiet '"abc_xyz_group":'

# 20. Cross-workspace ABC/XYZ isolation: user-1 accessing ws-2 returns 403
abc_xyz_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/summary")"
if [ "$abc_xyz_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace ABC/XYZ summary access, got $abc_xyz_cross_status" >&2
    exit 1
fi

# 21. Frontend UI renders ABC/XYZ view tab
abc_xyz_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/inventory?tab=abc-xyz")"
printf '%s' "$abc_xyz_page" | grep --quiet 'ABC / XYZ Анализ'

echo "Integration check passed: web -> analytics health, identity, workspace access boundaries, demo dataset, sales overview, drill-down detail records, inventory intelligence, and ABC/XYZ matrix analysis are verified."



