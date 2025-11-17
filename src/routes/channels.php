<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('account.{accountId}', function ($user, $accountId) {
    return $user->accounts()->where('id', $accountId)->exists();
});

Broadcast::channel('client.{clientId}', function ($user, $clientId) {
    return $user->client_id === (int) $clientId;
});
