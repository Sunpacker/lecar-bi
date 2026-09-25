<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $workspaceName,
        public string $inviteUrl,
        public string $roleName,
    ) {}

    public function build(): self
    {
        return $this->subject("Приглашение в рабочее пространство {$this->workspaceName}")
            ->html("
                <h2>Здравствуйте!</h2>
                <p>Вас пригласили присоединиться к рабочему пространству <strong>{$this->workspaceName}</strong> в роли <strong>{$this->roleName}</strong>.</p>
                <p><a href=\"{$this->inviteUrl}\" style=\"display: inline-block; padding: 10px 20px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;\">Принять приглашение</a></p>
                <p>Или перейдите по ссылке: <br>{$this->inviteUrl}</p>
                <p>Ссылка действительна в течение 7 дней.</p>
            ");
    }
}
