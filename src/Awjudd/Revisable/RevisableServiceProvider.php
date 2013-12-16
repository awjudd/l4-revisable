<?php namespace Awjudd\Revisable;

use Illuminate\Support\ServiceProvider;

class RevisableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('awjudd/revisable');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();   
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app['command.revisable.cleanup'] = $this->app->share(function($app)
        {
            return new CleanupCommand($app);
        });

        $this->commands(
            'command.revisable.cleanup'
        );
    }
}