<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\MasterDataObserver;
use App\Policies\DocumentPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\StockMovementPolicy;
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
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);

        foreach (array_keys(MasterDataObserver::SUBJECTS) as $model) {
            $model::observe(MasterDataObserver::class);
        }
    }
}
