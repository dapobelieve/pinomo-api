<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return $user && (string) $user->id === (string) $id;
});

Broadcast::channel('account.{accountId}', function ($user, $accountId) {
    return $user?->client?->accounts()->where('id', $accountId)->exists() ?? false;
});

Broadcast::channel('client.{clientId}', function ($user, $clientId) {
    return $user?->client?->id === $clientId;
});
