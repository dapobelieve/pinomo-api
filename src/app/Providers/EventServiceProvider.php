<?php

namespace App\Providers;

use App\Events\AccountCreated;
use App\Events\TransactionJobStarted;
use App\Events\LienReleaseCompleted;
use App\Events\ReleaseAndWithdrawCompleted;
use App\Events\TransactionJobFailed;
use App\Listeners\CreateRollingReserveAccount;
use App\Listeners\UpdateTransactionStatus;
use App\Listeners\SendWebhookNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
  /**
   * The event to listener mappings for the application.
   *
   * @var array<class-string, array<int, class-string>>
   */
  protected $listen = [
    Registered::class => [
      SendEmailVerificationNotification::class,
    ]
  ];

  /**
   * Register any events for your application.
   *
   * @return void
   */
  public function boot()
  {
    //
  }

  /**
   * Determine if events and listeners should be automatically discovered.
   *
   * @return bool
   */
  public function shouldDiscoverEvents()
  {
    return false;
  }
}
