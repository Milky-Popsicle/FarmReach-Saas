<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, string $id): bool {
    return (int) $user->getKey() === (int) $id;
});
