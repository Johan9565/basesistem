<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('notifications_create_office.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
