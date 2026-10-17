<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Morph Map
        |--------------------------------------------------------------------------
        | When an agent replies to a ticket or logs an activity, 
        | the database stores 'agent' instead of the hardcoded class string App\Models\Agent. 
        | If class namespaces ever change in the future, database data won't break.
        */
        Relation::enforceMorphMap([
            'admin'    => Admin::class,
            'agent'    => Agent::class,
            'customer' => Customer::class,
        ]);

        Model::preventLazyLoading(! app()->isProduction());

        \Illuminate\Support\Facades\Gate::policy(\App\Models\Ticket::class, \App\Policies\TicketPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Attachment::class, \App\Policies\AttachmentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Import::class, \App\Policies\ImportPolicy::class);
    }
}
