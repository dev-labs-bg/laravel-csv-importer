<?php namespace YavorIvanov\CsvImporter;

use Illuminate\Support\ServiceProvider;

class CsvImporterServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('yavor-ivanov/csv-importer');

        $this->app['csv::import'] = $this->app->share(function($app)
        {
            return new Commands\Import();
        });
        $this->app['csv::export'] = $this->app->share(function($app)
        {
            return new Commands\Export();
        });
        $this->app['csv::backup'] = $this->app->share(function($app)
        {
            return new Commands\Backup();
        });
        $this->commands(array('csv::import', 'csv::export', 'csv::backup'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['csv-importer'] = $this->app->share(function($app)
        {
            return new CSVImporter;
        });

        $this->app['csv-exporter'] = $this->app->share(function($app)
        {
            return new CSVExporter;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('csv-importer', 'csv-exporter');
    }

}
