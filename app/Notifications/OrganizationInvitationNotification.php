<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    protected OrganizationInvitation $invitation;

    /**
     * Create a new notification instance.
     */
    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation;
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
        $inviterName = $this->invitation->inviter->name;
        $orgName = $this->invitation->organization->name;
        $role = ucfirst($this->invitation->role);
        $expiresAt = $this->invitation->expires_at->format('M d, Y');

        // Generate invitation acceptance URL
        $acceptUrl = url('/api/v1/invitations/' . $this->invitation->token . '/accept');

        return (new MailMessage)
            ->subject('You have been invited to ' . $orgName)
            ->greeting('Hello!')
            ->line($inviterName . ' has invited you to join **' . $orgName . '** as a **' . $role . '**.')
            ->line('To accept this invitation, please register or log in and use the invitation token below:')
            ->line('**Invitation Token:** `' . $this->invitation->token . '`')
            ->line('Or click the button below to accept directly:')
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire on ' . $expiresAt . '.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_name' => $this->invitation->organization->name,
            'role' => $this->invitation->role,
            'invited_by' => $this->invitation->inviter->name,
            'expires_at' => $this->invitation->expires_at,
        ];
    }
}
