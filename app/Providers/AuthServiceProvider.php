<?php

namespace App\Providers;

use App\Models\Role;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
  /**
   * The policy mappings for the application.
   *
   * @var array
   */
  protected $policies = [];

  /**
   * Register any authentication / authorization services.
   *
   * @return void
   */
  public function boot()
  {
    // Implicitly grant "Super Admin" role all permissions
    Gate::before(function ($user, $ability) {
      return $user->hasRole(Role::SUPER_ADMIN, 'store') ? true : null;
    });
  }
}
