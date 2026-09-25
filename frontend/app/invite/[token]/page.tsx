import React from 'react'
import { BarChart3 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { AcceptInvitationView } from '@/src/features/workspace/ui/accept-invitation-view'

export const dynamic = 'force-dynamic'

export const metadata = {
  title: 'Приглашение в систему — AutoBI',
  description: 'Принятие приглашения в рабочее пространство AutoBI',
}

export default async function InvitePage({
  params,
}: {
  params: Promise<{ token: string }>
}) {
  const { token } = await params

  return (
    <main className="min-h-screen flex flex-col items-center justify-center p-4 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-background to-background">
      <div className="w-full max-w-md space-y-6">
        <div className="flex flex-col items-center text-center space-y-2">
          <div className="size-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shadow-lg shadow-emerald-500/5 mb-1">
            <BarChart3 className="size-6 text-emerald-400" />
          </div>
          <div className="flex items-center gap-2">
            <Badge
              variant="outline"
              className="text-emerald-400 border-emerald-500/30 text-[10px] uppercase font-mono tracking-wider"
            >
              AutoBI Workspace
            </Badge>
          </div>
        </div>

        <AcceptInvitationView token={token} />

        <p className="text-center text-[11px] text-muted-foreground/70">
          Платформа корпоративной бизнес-аналитики AutoBI &copy;{' '}
          {new Date().getFullYear()}
        </p>
      </div>
    </main>
  )
}
