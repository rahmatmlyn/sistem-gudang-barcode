<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\MasterDataObserver;
use App\Policies\MasterDataPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([Item::class, Location::class, Warehouse::class] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }
        Gate::policy(User::class, UserPolicy::class);

        foreach (array_keys(MasterDataObserver::SUBJECTS) as $model) {
            $model::observe(MasterDataObserver::class);
        }
    }
}
