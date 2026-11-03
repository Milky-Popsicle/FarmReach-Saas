<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the viewer may see the target user's private fields.
     */
    public function viewPrivate(User $viewer, User $target): bool
    {
        return $viewer->id === $target->id || $viewer->isAdmin();
    }
}
