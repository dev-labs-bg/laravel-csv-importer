<?php namespace YavorIvanov\CsvImporter\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use MJS\TopSort\Implementations\ArraySort;
use YavorIvanov\CsvImporter\CSVExporter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;


class Export extends Command
{
    protected $name = 'csv:export';
    protected $description = 'Exports a given model to a CSV file.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        create_dir_if_not_found(app_path() . \Config::get('csv-importer::export.register_path'));
        create_dir_if_not_found(app_path() . \Config::get('csv-importer::export.default_csv_path'));

        $model = strtolower($this->argument('model'));
        if ($model == 'all')
            $models = array_keys(CSVExporter::get_exporters());
        else
            $models = [$model];
        foreach ($models as $name)
        {
            $exporter = CSVExporter::get_exporter($name);

            // TODO <Yavor>: Create an argument validation function; Laravel seems to
            //               lack one.
            if (! $exporter)
            {
                $exporters = implode(', ', array_merge(array_keys(CSVExporter::get_exporters()), ['all']));
                $this->error("Invalid model name $name!\nValid models are: $exporters");
                return;
            }

            Artisan::call('csv:backup', ['model' => $name, '-q' => true]);
            $res = (new $exporter)->export(Null);
            $this->info('Exported: '. ucfirst($name) . '.');
        }
    }

    protected function getArguments()
    {
        return [
            [
                'model',
                InputArgument::REQUIRED, 'Specify which model to import. Valid models are: ' .
                implode(', ', array_merge(array_keys(CSVExporter::get_exporters()), ['all'])), Null
            ],
        ];
    }
}
