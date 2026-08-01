<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberAddedNotification extends Notification
{
    use Queueable;

    protected Organization $organization;
    protected string $role;
    protected User $addedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Organization $organization, string $role, User $addedBy)
    {
        $this->organization = $organization;
        $this->role = $role;
        $this->addedBy = $addedBy;
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
        return (new MailMessage)
            ->subject('You have been added to ' . $this->organization->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->addedBy->name . ' has added you to **' . $this->organization->name . '** as a **' . ucfirst($this->role) . '**.')
            ->line('You can now manage events and registrations for this organization.')
            ->action('View Organization', url('/organizations/' . $this->organization->uuid))
            ->line('Thank you for being part of our community!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'role' => $this->role,
            'added_by' => $this->addedBy->name,
        ];
    }
}
