<?php

namespace Lauthz;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lauthz\Facades\Enforcer;
use Lauthz\Loaders\LoaderManager;
use Lauthz\Models\Rule;
use Lauthz\Observers\RuleObserver;

class LauthzServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'laravel-lauthz-migrations');
            $this->publishes([
                __DIR__ . '/../config/lauthz-rbac-model.conf' => $this->app->basePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . ('lauthz-rbac-model.conf'),
                __DIR__ . '/../config/lauthz.php' => $this->app->basePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . ('lauthz.php'),
            ], 'laravel-lauthz-config');

            $this->commands([
                Commands\GroupAdd::class,
                Commands\PolicyAdd::class,
                Commands\RoleAssign::class,
            ]);
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/lauthz.php', 'lauthz');

        $this->bootObserver();
    }

    /**
     * Boot Observer.
     *
     * @return void
     */
    protected function bootObserver()
    {
        Rule::observe(new RuleObserver());
    }

    /**
     * Register bindings in the container.
     */
    public function register()
    {
        $this->app->singleton('enforcer', function ($app) {
            return new EnforcerManager($app);
        });

        $this->app->singleton(LoaderManager::class, function ($app) {
            return new LoaderManager($app);
        });

        $this->registerGates();
    }

    /**
     * Register a gate that allows users to use Laravel's built-in Gate to call Enforcer.
     *
     * @return void
     */
    protected function registerGates()
    {
        Gate::define('enforcer', function ($user, ...$args) {
            $identifier = $user->getAuthIdentifier();
            if (method_exists($user, 'getAuthzIdentifier')) {
                $identifier = $user->getAuthzIdentifier();
            }
            $identifier = strval($identifier);

            return Enforcer::enforce($identifier, ...$args);
        });
    }
}
