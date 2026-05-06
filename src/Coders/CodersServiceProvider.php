<?php

namespace Reliese\Coders;

use Reliese\Support\Classify;
use Reliese\Coders\Model\Config;
use Reliese\Meta\SchemaManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Reliese\Coders\Console\CodeModelsCommand;
use Reliese\Coders\Model\Factory as ModelFactory;

class CodersServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/models.php' => config_path('models.php'),
            ], 'reliese-models');

            $this->commands([
                CodeModelsCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCustomMappers();
        $this->registerModelFactory();
    }

    /**
     * Register any custom schema mappers defined in config/models.php.
     *
     * @return void
     */
    protected function registerCustomMappers()
    {
        $mappers = $this->app->make('config')->get('models.custom_mappers', []);

        foreach ($mappers as $connection => $mapper) {
            SchemaManager::register($connection, $mapper);
        }
    }

    /**
     * Register Model Factory.
     *
     * @return void
     */
    protected function registerModelFactory()
    {
        $this->app->singleton(ModelFactory::class, function ($app) {
            return new ModelFactory(
                $app->make('db'),
                $app->make(Filesystem::class),
                new Classify(),
                new Config($app->make('config')->get('models'))
            );
        });
    }

    /**
     * @return array
     */
    public function provides()
    {
        return [ModelFactory::class];
    }
}
