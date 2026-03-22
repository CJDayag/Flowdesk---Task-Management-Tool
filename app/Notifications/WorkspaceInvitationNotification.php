<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WorkspaceInvitation $invitation,
        private readonly string $token,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = URL::temporarySignedRoute(
            'workspace-invitations.show',
            $this->invitation->expires_at,
            ['invitation' => $this->invitation->id, 'token' => $this->token],
        );

        return (new MailMessage)
            ->subject('You have been invited to a workspace')
            ->greeting('Workspace Invitation')
            ->line("You were invited to join {$this->invitation->workspace->name}.")
            ->line('Use the secure link below to review and accept the invitation.')
            ->action('Review Invitation', $acceptUrl)
            ->line('If you were not expecting this invite, you can safely ignore this email.');
    }
}
