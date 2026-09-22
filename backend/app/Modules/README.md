# Bounded contexts

Каждый модуль создаётся внутри `app/Modules/<Context>` и содержит `Domain`, `Application`, `Infrastructure`, `Presentation`. На этапе каркаса модули не содержат бизнес-реализаций.

Запланированные контексты: Workspace, DataIngestion, SalesAnalytics, InventoryAnalytics, SupplierAnalytics, Dashboard, Alerting.
