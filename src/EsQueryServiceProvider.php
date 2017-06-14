<?php

namespace Huasituo\Es;

use Illuminate\Support\ServiceProvider;

class EsQueryServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Temp to use in closure.
        $app = $this->app;

        $this->app['es'] = $this->app->share(function ($app) {
            return new EsQuery($app);
        });
    }
}
