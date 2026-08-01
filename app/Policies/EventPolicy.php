<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can list events
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        // Organization members can view any event in their org
        return $event->organization->hasMember($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organization $organization): bool
    {
        // User must be at least a member of the organization
        return $organization->hasMember($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        // Admins and owners can update events
        return $event->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Admins and owners can delete events
        return $event->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $event->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $event->organization->isOwner($user);
    }

    /**
     * Determine whether the user can manage registrations.
     */
    public function manageRegistrations(User $user, Event $event): bool
    {
        return $event->organization->hasMember($user);
    }

    /**
     * Determine whether the user can check in attendees.
     */
    public function checkIn(User $user, Event $event): bool
    {
        // Any organization member can check in attendees
        return $event->organization->hasMember($user);
    }
}
