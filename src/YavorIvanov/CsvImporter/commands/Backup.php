<?php namespace YavorIvanov\CsvImporter\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YavorIvanov\CsvImporter\CSVImporter;

class Backup  extends Command
{
    protected $name = 'csv:backup';
    protected $description = 'Backs a given CSV file up.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        create_dir_if_not_found(app_path() . \Config::get('csv-importer::export.backup_csv_path'));

        $model = strtolower($this->argument('model'));
        if ($model == 'all')
            $models = CSVImporter::sort_importers(CSVImporter::get_importers());
        else
            $models = [$model];
        foreach ($models as $model)
        {
            $importer = CSVImporter::get_importer($model);
            if (! $importer)
            {
                $importers = implode(', ', array_merge(array_keys(CSVImporter::get_importers()), ['all']));
                $this->error("Invalid model name $model!\nValid models are: $importers");
                return;
            }
            $file = (new $importer)->file;
            if (file_exists($file))
            {
                copy($file, app_path() . \Config::get('csv-importer::export.backup_csv_path') . date('Y_m_d_His_') . basename($file));
                if (! $this->option('silent'))
                    $this->info('Backed up: '. ucfirst($model) . '.');
            }
            else
            {
                if (! $this->option('silent'))
                    $this->info('File: '. $file . ' does not exist or permissions are insufficient.');
            }
        }
    }

    protected function getArguments()
    {
        return [
            [
                'model',
                InputArgument::REQUIRED, 'Specify which model to backup. Valid models are: ' .
                implode(', ', array_merge(array_keys(CSVImporter::get_importers()), ['all'])), Null
            ],
        ];
    }

    protected function getOptions()
    {
        return [
            [
                'silent', null, InputOption::VALUE_NONE,
                'If passed, shows no output.'
            ],
        ];
    }
}
