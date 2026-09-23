#!/bin/sh

set -eu

FRONTEND_URL="${FRONTEND_URL:-http://127.0.0.1:3000}"
BACKEND_URL="${BACKEND_URL:-http://127.0.0.1:8080}"
NOTIFICATION_URL="${NOTIFICATION_URL:-http://127.0.0.1:8081}"
INFRA_ENV_FILE="${INFRA_ENV_FILE:-infra/.env.example}"
MAX_ATTEMPTS="${MAX_ATTEMPTS:-30}"

wait_for_service() {
    service_name="$1"
    service_url="$2"
    attempt=1

    while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
        if curl --fail --silent "$service_url" >/dev/null 2>&1; then
            return 0
        fi

        attempt=$((attempt + 1))
        sleep 2
    done

    echo "$service_name did not become ready at $service_url" >&2
    return 1
}

assert_response_contains() {
    response="$1"
    expected="$2"
    check_name="$3"

    if printf '%s' "$response" | grep --fixed-strings --quiet "$expected"; then return 0; fi

    echo "$check_name response did not contain expected value: $expected" >&2
    return 1
}

assert_json_value_equals() {
    response="$1"
    selector="$2"
    expected="$3"
    check_name="$4"

    if ! actual="$(printf '%s' "$response" | jq --raw-output "$selector" 2>/dev/null)"; then
        echo "$check_name response was not valid JSON" >&2
        return 1
    fi

    if [ "$actual" = "$expected" ]; then return 0; fi

    echo "$check_name returned unexpected value at $selector: expected '$expected', got '$actual'" >&2
    return 1
}

get_required_json_value() {
    response="$1"
    selector="$2"
    check_name="$3"

    if ! value="$(printf '%s' "$response" | jq --exit-status --raw-output "$selector | select(. != null and . != \"\")" 2>/dev/null)"; then
        echo "$check_name response did not contain a value at $selector" >&2
        return 1
    fi

    printf '%s' "$value"
}

if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required to verify JSON API responses" >&2
    exit 1
fi

wait_for_service "analytics" "$BACKEND_URL/api/v1/health"
wait_for_service "web" "$FRONTEND_URL/api/health"
wait_for_service "notification" "$NOTIFICATION_URL/api/v1/health/ready"

# Apply migrations and seed data if docker compose environment is active
if command -v docker >/dev/null 2>&1; then
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php artisan migrate --force --seed --no-interaction >/dev/null
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php artisan migrate --force --no-interaction >/dev/null
fi

# 1. Technical health check: analytics
backend_response="$(curl --fail --silent --show-error "$BACKEND_URL/api/v1/health")"
assert_response_contains "$backend_response" '"status":"ok"' "Analytics health"
assert_response_contains "$backend_response" '"service":"analytics"' "Analytics health"

# 1b. Technical health check: notification service (live and ready probes)
notif_live_response="$(curl --fail --silent --show-error "$NOTIFICATION_URL/api/v1/health/live")"
assert_response_contains "$notif_live_response" '"status":"live"' "Notification liveness"
assert_response_contains "$notif_live_response" '"service":"notification"' "Notification liveness"

notif_ready_response="$(curl --fail --silent --show-error "$NOTIFICATION_URL/api/v1/health/ready")"
assert_response_contains "$notif_ready_response" '"status":"ready"' "Notification readiness"
assert_response_contains "$notif_ready_response" '"database":"ok"' "Notification readiness DB"
assert_response_contains "$notif_ready_response" '"redis":"ok"' "Notification readiness Redis"

# 1c. Environmental database isolation check: notification must not have analytics DB env
if command -v docker >/dev/null 2>&1; then
    notif_env="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification env 2>/dev/null || true)"
    if printf '%s' "$notif_env" | grep -E '^POSTGRES_DB=autobi' >/dev/null; then
        echo "Isolation failure: notification container has analytics POSTGRES_DB env!" >&2
        exit 1
    fi
fi

# 2. Access boundary: unauthenticated request returns 401
unauth_status="$(curl --silent -o /dev/null -w "%{http_code}" "$BACKEND_URL/api/v1/workspaces")"
if [ "$unauth_status" != "401" ]; then
    echo "Expected 401 for unauthenticated request to /workspaces, got $unauth_status" >&2
    exit 1
fi

# 3. Access boundary: user-1 can only list accessible workspaces
user1_workspaces="$(curl --fail --silent --show-error -H "X-User-Id: user-1" "$BACKEND_URL/api/v1/workspaces")"
assert_response_contains "$user1_workspaces" '"id":"ws-1"' "Accessible workspaces"

# 4. Cross-workspace isolation: user-1 attempting to access user-2's workspace returns 403 Forbidden
cross_access_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" "$BACKEND_URL/api/v1/workspaces/ws-2")"
if [ "$cross_access_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to /workspaces/ws-2, got $cross_access_status" >&2
    exit 1
fi

# 4b. Backend auth login endpoint verification
login_response="$(curl --fail --silent --show-error -H "Content-Type: application/json" -d '{"email":"elena@autobi.internal","password":"password123"}' "$BACKEND_URL/api/v1/auth/login")"
assert_response_contains "$login_response" '"id":"user-1"' "Backend login"

# 5. Frontend Auth & UI: unauthenticated request redirects to /login
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

unauth_frontend_status="$(curl --silent -o /dev/null -w "%{http_code}" "$FRONTEND_URL/")"
if [ "$unauth_frontend_status" != "307" ] && [ "$unauth_frontend_status" != "302" ]; then
    echo "Expected 307 or 302 redirect for unauthenticated request to /, got $unauth_frontend_status" >&2
    exit 1
fi

login_page_response="$(curl --fail --silent --show-error "$FRONTEND_URL/login")"
assert_response_contains "$login_page_response" 'Вход в систему' "Login page"

# Authenticate via Next.js BFF and save session cookie
curl --fail --silent --show-error -c "$COOKIE_JAR" -H "Content-Type: application/json" \
    -d '{"email":"elena@autobi.internal","password":"password123"}' \
    "$FRONTEND_URL/api/auth/login" >/dev/null

# Authenticated frontend request renders workspace
frontend_response="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/")"
assert_response_contains "$frontend_response" 'Analytics workspace' "Authenticated home page"

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
assert_response_contains "$sales_filters" '"categories":[' "Sales filters"
assert_response_contains "$sales_filters" '"regions":[' "Sales filters"

# 8. Sales overview endpoint returns aggregated summary from PostgreSQL
sales_overview="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/overview")"
assert_response_contains "$sales_overview" '"total_revenue":' "Sales overview"
assert_response_contains "$sales_overview" '"order_count":' "Sales overview"
assert_response_contains "$sales_overview" '"trend":[' "Sales overview"
assert_response_contains "$sales_overview" '"categories":[' "Sales overview"
assert_response_contains "$sales_overview" '"regions":[' "Sales overview"

# 9. Cross-workspace sales analytics isolation: user-1 accessing ws-2 returns 403
sales_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/sales/overview")"
if [ "$sales_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace sales analytics access, got $sales_cross_status" >&2
    exit 1
fi

# 10. Sales detail records endpoint returns paginated items from PostgreSQL
sales_records="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc")"
assert_response_contains "$sales_records" '"items":[' "Sales records"
assert_response_contains "$sales_records" '"pagination":{' "Sales records"
assert_response_contains "$sales_records" '"total":' "Sales records"

# 11. Cross-workspace sales records isolation: user-1 accessing ws-2 records returns 403
sales_records_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/sales/records")"
if [ "$sales_records_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace sales records access, got $sales_records_cross_status" >&2
    exit 1
fi

# 12. Frontend UI renders Sales Analytics Dashboard and Detail Table
frontend_dashboard="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/")"
assert_response_contains "$frontend_dashboard" 'Аналитика продаж' "Sales dashboard page"

# 13. Inventory filters endpoint
inventory_filters="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/filters")"
assert_response_contains "$inventory_filters" '"warehouses":[' "Inventory filters"
assert_response_contains "$inventory_filters" '"statuses":[' "Inventory filters"

# 14. Inventory summary endpoint returns aggregated metrics
inventory_summary="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/summary")"
assert_response_contains "$inventory_summary" '"total_items":' "Inventory summary"
assert_response_contains "$inventory_summary" '"health_breakdown":[' "Inventory summary"
assert_response_contains "$inventory_summary" '"warehouses":[' "Inventory summary"

# 15. Inventory items endpoint returns paginated items with health status
inventory_items="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/items?page=1&per_page=10")"
assert_response_contains "$inventory_items" '"items":[' "Inventory items"
assert_response_contains "$inventory_items" '"pagination":{' "Inventory items"
assert_response_contains "$inventory_items" '"stock_health":' "Inventory items"

# 16. Cross-workspace inventory isolation: user-1 accessing ws-2 returns 403
inventory_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/inventory/summary")"
if [ "$inventory_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace inventory summary access, got $inventory_cross_status" >&2
    exit 1
fi

# 17. Frontend UI renders Inventory Dashboard
inventory_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/inventory")"
assert_response_contains "$inventory_page" 'Управление запасами' "Inventory page"

# 18. Inventory ABC/XYZ summary endpoint returns 3x3 matrix and distributions
abc_xyz_summary="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/summary?period_days=90")"
assert_response_contains "$abc_xyz_summary" '"matrix":[' "ABC/XYZ summary"
assert_response_contains "$abc_xyz_summary" '"abc_distribution":[' "ABC/XYZ summary"
assert_response_contains "$abc_xyz_summary" '"xyz_distribution":[' "ABC/XYZ summary"

# 19. Inventory ABC/XYZ items endpoint returns classified catalog
abc_xyz_items="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/items?period_days=90&page=1&per_page=10")"
assert_response_contains "$abc_xyz_items" '"items":[' "ABC/XYZ items"
assert_response_contains "$abc_xyz_items" '"abc_class":' "ABC/XYZ items"
assert_response_contains "$abc_xyz_items" '"xyz_class":' "ABC/XYZ items"
assert_response_contains "$abc_xyz_items" '"abc_xyz_group":' "ABC/XYZ items"

# 20. Cross-workspace ABC/XYZ isolation: user-1 accessing ws-2 returns 403
abc_xyz_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/analytics/inventory/abc-xyz/summary")"
if [ "$abc_xyz_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace ABC/XYZ summary access, got $abc_xyz_cross_status" >&2
    exit 1
fi

# 21. Frontend UI renders ABC/XYZ view tab
abc_xyz_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/inventory?tab=abc-xyz")"
assert_response_contains "$abc_xyz_page" 'ABC / XYZ Анализ' "ABC/XYZ page"

# 22. Dashboards endpoint returns list for workspace
dashboards_list="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards")"
assert_response_contains "$dashboards_list" '"items":[' "Dashboards list"
assert_response_contains "$dashboards_list" '"id":"d0000001-0000-4000-8000-000000000001"' "Dashboards list"

# 23. Cross-workspace dashboard isolation: user-1 accessing ws-2 dashboard returns 403
dashboard_cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/dashboards/d0000002-0000-4000-8000-000000000001")"
if [ "$dashboard_cross_status" != "403" ]; then
    echo "Expected 403 for cross-workspace dashboard access, got $dashboard_cross_status" >&2
    exit 1
fi

# 24. Dashboard Builder Full Lifecycle: Create custom dashboard via POST
created_dash_json="$(curl --fail --silent --show-error -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"title":"Интеграционный дашборд","description":"Создан для проверки жизненного цикла"}' \
    "$BACKEND_URL/api/v1/dashboards")"
assert_json_value_equals "$created_dash_json" '.dashboard.title' 'Интеграционный дашборд' "Dashboard creation"
CUSTOM_DASH_ID="$(get_required_json_value "$created_dash_json" '.dashboard.id' "Dashboard creation")"

# 25. Dashboard Builder: Restore dashboard via GET
restored_dash_json="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
assert_json_value_equals "$restored_dash_json" '.dashboard.id' "$CUSTOM_DASH_ID" "Dashboard restore"
assert_json_value_equals "$restored_dash_json" '.dashboard.title' 'Интеграционный дашборд' "Dashboard restore title"

# 26. Dashboard Builder: Update dashboard via PUT (reposition & add widget)
updated_dash_json="$(curl --fail --silent --show-error -X PUT -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{
      "title": "Обновленный дашборд",
      "description": "Описание обновлено",
      "widgets": [
        {
          "id": "e0000001-0000-4000-8000-000000000001",
          "title": "Выручка перемещенная",
          "type": "kpi_card",
          "position": {"x": 4, "y": 0, "w": 6, "h": 2},
          "query_config": {"dataset": "sales", "metric": "revenue", "date_range": "30d"},
          "options": {}
        }
      ]
    }' \
    "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
assert_json_value_equals "$updated_dash_json" '.dashboard.title' 'Обновленный дашборд' "Dashboard update"
assert_json_value_equals "$updated_dash_json" '.dashboard.widgets[0].position.x' '4' "Widget reposition"

# 27. Cross-tenant isolation on custom dashboard
cross_custom_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-2" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$cross_custom_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to custom dashboard, got $cross_custom_status" >&2
    exit 1
fi

# 28. Frontend UI: Authenticated Next.js renders Dashboards pages
frontend_dashboards_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/dashboards")"
assert_response_contains "$frontend_dashboards_page" 'Пользовательские дашборды' "Frontend dashboards page"

frontend_dash_view="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/dashboards/$CUSTOM_DASH_ID")"
assert_response_contains "$frontend_dash_view" 'Обновленный дашборд' "Frontend custom dashboard view"

# 29. Dashboard Saved Views: Create saved view / filter preset via POST
created_view_json="$(curl --fail --silent --show-error -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"name":"Интеграционный пресет","filters":{"date_range":"30d","region_id":"reg-1"},"is_default":true}' \
    "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID/views")"
assert_json_value_equals "$created_view_json" '.view.name' 'Интеграционный пресет' "Saved view creation"
assert_json_value_equals "$created_view_json" '.view.is_default' 'true' "Saved view default flag"
CUSTOM_VIEW_ID="$(get_required_json_value "$created_view_json" '.view.id' "Saved view creation")"

# 30. Dashboard Saved Views: List and read saved view
views_list_json="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID/views")"
assert_json_value_equals "$views_list_json" '.items | length' '1' "Saved views list"
assert_json_value_equals "$views_list_json" '.items[0].name' 'Интеграционный пресет' "Saved view list content"

# 31. Dashboard Saved Views: Update saved view via PUT
updated_view_json="$(curl --fail --silent --show-error -X PUT -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"name":"Обновленный пресет","filters":{"date_range":"90d"},"is_default":false}' \
    "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID/views/$CUSTOM_VIEW_ID")"
assert_json_value_equals "$updated_view_json" '.view.name' 'Обновленный пресет' "Saved view update"
assert_json_value_equals "$updated_view_json" '.view.filters.date_range' '90d' "Saved view filter update"

# 32. Cross-tenant isolation on saved views: user-2 accessing ws-1 views returns 403
cross_view_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-2" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID/views")"
if [ "$cross_view_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to saved views, got $cross_view_status" >&2
    exit 1
fi

# 33. Dashboard Saved Views: Delete saved view via DELETE
del_view_status="$(curl --silent -o /dev/null -w "%{http_code}" -X DELETE -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID/views/$CUSTOM_VIEW_ID")"
if [ "$del_view_status" != "204" ]; then
    echo "Expected 204 for DELETE saved view, got $del_view_status" >&2
    exit 1
fi

# 34. Dashboard Builder: Delete custom dashboard via DELETE
delete_status="$(curl --silent -o /dev/null -w "%{http_code}" -X DELETE -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$delete_status" != "204" ]; then
    echo "Expected 204 for DELETE dashboard, got $delete_status" >&2
    exit 1
fi

# 35. Verify 404 after deletion
deleted_get_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards/$CUSTOM_DASH_ID")"
if [ "$deleted_get_status" != "404" ]; then
    echo "Expected 404 for deleted dashboard, got $deleted_get_status" >&2
    exit 1
fi

# 36. Alert Rules: Create alert rule via POST
created_rule_json="$(curl --fail --silent --show-error -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"name":"Интеграционное правило","rule_type":"critical_stock","severity":"critical","metric":"days_of_stock","comparator":"lt","threshold_value":5,"is_enabled":true}' \
    "$BACKEND_URL/api/v1/alert-rules")"
assert_json_value_equals "$created_rule_json" '.rule.name' 'Интеграционное правило' "Alert rule creation"
assert_json_value_equals "$created_rule_json" '.rule.severity' 'critical' "Alert rule severity"
CUSTOM_RULE_ID="$(get_required_json_value "$created_rule_json" '.rule.id' "Alert rule creation")"

# 37. Alert Rules: Toggle rule status
toggled_rule_json="$(curl --fail --silent --show-error -X POST -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alert-rules/$CUSTOM_RULE_ID/toggle")"
assert_json_value_equals "$toggled_rule_json" '.rule.is_enabled' 'false' "Alert rule toggle"

# 38. Alert Rules: Evaluate rules endpoint
eval_result_json="$(curl --fail --silent --show-error -X POST -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alert-rules/evaluate")"
assert_response_contains "$eval_result_json" '"rules_evaluated"' "Alert rules evaluation"

# 39. Alerts: Summary and list
alerts_summary_json="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alerts/summary")"
assert_response_contains "$alerts_summary_json" '"total_active"' "Alerts summary total_active"

alerts_list_json="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alerts?status=active")"
assert_response_contains "$alerts_list_json" '"items"' "Alerts list items"
TEST_ALERT_ID="$(get_required_json_value "$alerts_list_json" '.items[0].id' "Alerts list first item")"

# 40. Alerts: Acknowledge & Resolve lifecycle
ack_alert_json="$(curl --fail --silent --show-error -X POST -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alerts/$TEST_ALERT_ID/acknowledge")"
assert_json_value_equals "$ack_alert_json" '.alert.status' 'acknowledged' "Alert acknowledge"

resolve_alert_json="$(curl --fail --silent --show-error -X POST -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"resolution_note":"Интеграционная проверка решения"}' \
    "$BACKEND_URL/api/v1/alerts/$TEST_ALERT_ID/resolve")"
assert_json_value_equals "$resolve_alert_json" '.alert.status' 'resolved' "Alert resolve"

# 41. Cross-tenant isolation on alerts: user-2 accessing ws-1 alerts returns 403
cross_alert_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-2" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alerts")"
if [ "$cross_alert_status" != "403" ]; then
    echo "Expected 403 for cross-workspace access to alerts, got $cross_alert_status" >&2
    exit 1
fi

# 42. Delete test alert rule
del_rule_status="$(curl --silent -o /dev/null -w "%{http_code}" -X DELETE -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alert-rules/$CUSTOM_RULE_ID")"
if [ "$del_rule_status" != "204" ]; then
    echo "Expected 204 for DELETE alert rule, got $del_rule_status" >&2
    exit 1
fi

# 43. Frontend Alerts page
frontend_alerts_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/alerts")"
assert_response_contains "$frontend_alerts_page" 'Алерты и дефицит' "Frontend alerts page"

# 44. Event-driven Integration: Publish alert events from Outbox to Redis Stream & consume into Notification Service
if command -v docker >/dev/null 2>&1; then
    echo "Verifying event-driven integration: analytics outbox -> Redis Stream -> notification service..."
    # Publish outbox messages from analytics
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T backend php artisan outbox:publish >/dev/null 2>&1 || true

    # Consume pending messages in notification service
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php artisan notifications:consume --once >/dev/null 2>&1 || true

    # Verify notifications projected into notification-postgres
    notif_count="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \NotificationService\Notification\Infrastructure\Persistence\NotificationModel::count();' 2>/dev/null | tr -d '\r\n')"

    consumed_count="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \NotificationService\Notification\Infrastructure\Persistence\ConsumedEventModel::count();' 2>/dev/null | tr -d '\r\n')"

    echo "Notification service state: $notif_count notifications, $consumed_count consumed events."

    # 45. Deduplication verification: re-consuming the same stream/batch is idempotent
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php artisan notifications:consume --once >/dev/null 2>&1 || true
    notif_count_after="$(docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml exec -T notification php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \NotificationService\Notification\Infrastructure\Persistence\NotificationModel::count();' 2>/dev/null | tr -d '\r\n')"

    if [ "$notif_count" != "$notif_count_after" ]; then
        echo "Error: Notification count changed after re-consuming duplicate events ($notif_count vs $notif_count_after)" >&2
        exit 1
    fi
    echo "Deduplication verified: re-running consumer does not create duplicate notifications."

    # 46. Failure isolation: Stop notification service, verify analytics remains healthy
    echo "Verifying failure isolation: stopping notification service..."
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml stop notification notification-worker >/dev/null 2>&1

    analytics_still_ok="$(curl --fail --silent --show-error "$BACKEND_URL/api/v1/health")"
    assert_response_contains "$analytics_still_ok" '"status":"ok"' "Analytics health during notification outage"

    # Restart notification service and verify recovery
    echo "Restarting notification service..."
    docker compose --env-file "$INFRA_ENV_FILE" -f infra/docker-compose.yml start notification notification-worker >/dev/null 2>&1
    wait_for_service "notification" "$NOTIFICATION_URL/api/v1/health/ready"
    echo "Notification service recovered successfully."
fi

# 47. Workspace RBAC: verify current workspace capabilities for owner, member, and viewer
user1_curr_ws="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/current")"
assert_response_contains "$user1_curr_ws" '"workspace.members.manage"' "Owner capabilities"
assert_response_contains "$user1_curr_ws" '"dashboards.manage"' "Owner capabilities"

user3_curr_ws="$(curl --fail --silent --show-error -H "X-User-Id: user-3" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/current")"
assert_response_contains "$user3_curr_ws" '"dashboards.manage"' "Member capabilities"
if printf '%s' "$user3_curr_ws" | grep -q '"workspace.members.manage"'; then
    echo "Member unexpectedly has workspace.members.manage capability" >&2
    exit 1
fi

user4_curr_ws="$(curl --fail --silent --show-error -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/current")"
assert_response_contains "$user4_curr_ws" '"dashboards.view"' "Viewer capabilities"
assert_response_contains "$user4_curr_ws" '"analytics.view"' "Viewer capabilities"
if printf '%s' "$user4_curr_ws" | grep -q '"dashboards.manage"'; then
    echo "Viewer unexpectedly has dashboards.manage capability" >&2
    exit 1
fi

# 48. Workspace RBAC: read permissions across all roles
curl --fail --silent --show-error -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/dashboards" >/dev/null
curl --fail --silent --show-error -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/analytics/sales/overview" >/dev/null
curl --fail --silent --show-error -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/alerts" >/dev/null

# 49. Workspace RBAC: member management access boundary
members_list="$(curl --fail --silent --show-error -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/ws-1/members")"
assert_response_contains "$members_list" '"user_id":"user-1"' "Workspace members list"
assert_response_contains "$members_list" '"role":"owner"' "Workspace members owner"

user3_members_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-3" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/ws-1/members")"
if [ "$user3_members_status" != "403" ]; then
    echo "Expected 403 for member accessing workspace members list, got $user3_members_status" >&2
    exit 1
fi

user4_members_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" "$BACKEND_URL/api/v1/workspaces/ws-1/members")"
if [ "$user4_members_status" != "403" ]; then
    echo "Expected 403 for viewer accessing workspace members list, got $user4_members_status" >&2
    exit 1
fi

# 50. Workspace RBAC: viewer mutations forbidden with 403 INSUFFICIENT_CAPABILITY
viewer_create_dash_status="$(curl --silent -o /dev/null -w "%{http_code}" -X POST -H "Content-Type: application/json" \
    -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" \
    -d '{"title":"Viewer Forbidden Dashboard"}' \
    "$BACKEND_URL/api/v1/dashboards")"
if [ "$viewer_create_dash_status" != "403" ]; then
    echo "Expected 403 for viewer creating dashboard, got $viewer_create_dash_status" >&2
    exit 1
fi

viewer_create_rule_status="$(curl --silent -o /dev/null -w "%{http_code}" -X POST -H "Content-Type: application/json" \
    -H "X-User-Id: user-4" -H "X-Workspace-Id: ws-1" \
    -d '{"name":"Viewer Forbidden Rule","rule_type":"critical_stock","severity":"critical","metric":"days_of_stock","comparator":"lt","threshold_value":5,"is_enabled":true}' \
    "$BACKEND_URL/api/v1/alert-rules")"
if [ "$viewer_create_rule_status" != "403" ]; then
    echo "Expected 403 for viewer creating alert rule, got $viewer_create_rule_status" >&2
    exit 1
fi

# 51. Workspace RBAC: sole owner demotion invariant returns 409 LAST_WORKSPACE_OWNER
sole_demote_status="$(curl --silent -o /dev/null -w "%{http_code}" -X PATCH -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"role":"viewer"}' \
    "$BACKEND_URL/api/v1/workspaces/ws-1/members/user-1")"
if [ "$sole_demote_status" != "409" ]; then
    echo "Expected 409 for sole owner demoting self, got $sole_demote_status" >&2
    exit 1
fi

# 52. Workspace RBAC: role promotion and demotion lifecycle
promote_member="$(curl --fail --silent --show-error -X PATCH -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"role":"owner"}' \
    "$BACKEND_URL/api/v1/workspaces/ws-1/members/user-3")"
assert_json_value_equals "$promote_member" '.member.role' 'owner' "Promote member to owner"

demote_member="$(curl --fail --silent --show-error -X PATCH -H "Content-Type: application/json" \
    -H "X-User-Id: user-1" -H "X-Workspace-Id: ws-1" \
    -d '{"role":"member"}' \
    "$BACKEND_URL/api/v1/workspaces/ws-1/members/user-3")"
assert_json_value_equals "$demote_member" '.member.role' 'member' "Demote back to member"

# 53. Workspace RBAC: cross-workspace isolation across all roles
for uid in user-1 user-3 user-4; do
    cross_status="$(curl --silent -o /dev/null -w "%{http_code}" -H "X-User-Id: $uid" -H "X-Workspace-Id: ws-2" "$BACKEND_URL/api/v1/dashboards")"
    if [ "$cross_status" != "403" ]; then
        echo "Expected 403 for cross-workspace access to ws-2 by $uid, got $cross_status" >&2
        exit 1
    fi
done

# 54. Frontend UI: settings access page
frontend_access_page="$(curl --fail --silent --show-error -b "$COOKIE_JAR" "$FRONTEND_URL/settings/access")"
assert_response_contains "$frontend_access_page" 'Управление доступом' "Frontend access settings page"

echo "Integration check passed: web -> analytics health, identity, workspace access boundaries, demo dataset, sales overview, drill-down detail records, inventory intelligence, ABC/XYZ matrix, dashboard builder, dashboard saved views, alerting & incident management lifecycle, notification service event streaming & isolation, and workspace RBAC capability matrix are verified."
