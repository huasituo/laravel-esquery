<?php

namespace Huasituo\Es;

use Illuminate\Support\ServiceProvider;

class EsQueryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function boot()
    {
        $this->app->singleton('es', function ($app) {
            return new EsQuery($app);
        });
    }

    /**
     * Register the application.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function register()
    {
        $this->registerAliases();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function provides()
    {
        return ['es'];
    }

    /**
     * Register the application aliases.
     *
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    protected function registerAliases()
    {
        $aliases = [
            'es' => ['\\Huasituo\\Es\\EsQuery'],
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->app->alias($key, $alias);
            }
        }
    }
}
