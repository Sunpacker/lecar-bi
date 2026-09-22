import { loadHealthStatus } from '../src/features/system-health/model/load-health-status'
import { HealthStatus } from '../src/features/system-health/ui/health-status'

export const dynamic = 'force-dynamic'

export default async function HomePage() {
  const healthStatus = await loadHealthStatus()

  return (
    <main className="shell">
      <span className="eyebrow">AUTOBI / DEVELOPMENT FOUNDATION</span>
      <h1>Analytics workspace</h1>
      <p>
        Frontend работает через версионированный контракт и проверяет доступность
        analytics-сервиса на сервере.
      </p>
      <HealthStatus status={healthStatus} />
    </main>
  )
}
