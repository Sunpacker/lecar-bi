'use client'

import React, { useState } from 'react'
import { UserPlus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { WorkspaceMemberList } from './workspace-member-list'
import { WorkspaceInvitationsList } from './workspace-invitations-list'
import { InviteMemberDialog } from './invite-member-dialog'
import { type Invitation } from '../api/workspace-gateway'

interface WorkspaceAccessViewProps {
  workspaceId: string
  currentUserId: string
  canManage: boolean
}

export function WorkspaceAccessView({
  workspaceId,
  currentUserId,
  canManage,
}: WorkspaceAccessViewProps) {
  const [isInviteOpen, setIsInviteOpen] = useState(false)
  const [lastAddedInvitation, setLastAddedInvitation] = useState<Invitation | null>(null)

  return (
    <div className="space-y-8">
      {/* Members Section */}
      <div className="space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-foreground">Текущие участники</h3>
            <p className="text-xs text-muted-foreground">
              Список пользователей, имеющих доступ к этому рабочему пространству.
            </p>
          </div>

          {canManage && (
            <Button
              onClick={() => setIsInviteOpen(true)}
              className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2 cursor-pointer self-start sm:self-auto"
            >
              <UserPlus className="h-4 w-4" />
              <span>Пригласить участника</span>
            </Button>
          )}
        </div>

        <WorkspaceMemberList workspaceId={workspaceId} currentUserId={currentUserId} />
      </div>

      {/* Invitations Section */}
      {canManage && (
        <div className="space-y-4 pt-4 border-t border-border">
          <div>
            <h3 className="text-lg font-semibold text-foreground">
              Ожидающие приглашения
            </h3>
            <p className="text-xs text-muted-foreground">
              Приглашения, отправленные по email и ещё не принятые получателями.
            </p>
          </div>

          <WorkspaceInvitationsList
            workspaceId={workspaceId}
            canManage={canManage}
            lastAddedInvitation={lastAddedInvitation}
          />
        </div>
      )}

      {/* Invite Modal */}
      {canManage && (
        <InviteMemberDialog
          open={isInviteOpen}
          onOpenChange={setIsInviteOpen}
          workspaceId={workspaceId}
          onInvitationCreated={(inv) => {
            setLastAddedInvitation(inv)
          }}
        />
      )}
    </div>
  )
}
