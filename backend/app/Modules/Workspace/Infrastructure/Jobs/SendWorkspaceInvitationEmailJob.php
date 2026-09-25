<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Jobs;

use App\Modules\Workspace\Infrastructure\Mail\WorkspaceInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendWorkspaceInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $workspaceName,
        #[\SensitiveParameter]
        public string $token,
        public string $role,
    ) {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $appUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3000')), '/');
        $inviteUrl = "{$appUrl}/invite/{$this->token}";
        $roleName = $this->role === 'member' ? 'Участник' : 'Наблюдатель';

        Mail::to($this->email)->send(
            new WorkspaceInvitationMail(
                workspaceName: $this->workspaceName,
                inviteUrl: $inviteUrl,
                roleName: $roleName,
            )
        );
    }
}
