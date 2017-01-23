<?php namespace YavorIvanov\CsvImporter\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YavorIvanov\CsvImporter\CSVImporter;
use Illuminate\Support\Facades\Event;

class Import extends Command
{
    protected $name = 'csv:import';
    protected $description = 'Imports a given CSV file into the database.';
    protected $progressbar;

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        Event::listen('import.start', function($row_count, $name)
        {
            $this->progressbar = $this->getHelperSet()->get('progress');
            $this->progressbar->start($this->output, $row_count);
        });

        Event::listen('import.update.progress', function()
        {
            $this->progressbar->advance();
        });

        Event::listen('import.complete', function()
        {
            $this->progressbar->finish();
        });

        $mode = strtolower($this->argument('mode'));
        $model = strtolower($this->argument('model'));
        if ($model == 'all')
            $models = CSVImporter::sort_importers(CSVImporter::get_importers());
        else
            $models = [$model];
        foreach ($models as $model)
        {
            if (! $this->option('silent'))
                $this->info('Importing: '. ucfirst($model) . '.');
            $importer = CSVImporter::get_importer($model);

            // TODO <Yavor>: Create an argument validation function; Laravel seems to
            //               lack one.
            if (! $importer)
            {
                $importers = implode(', ', array_merge(array_keys(CSVImporter::get_importers()), ['all']));
                $this->error("Invalid model name $model!\nValid models are: $importers");
                return;
            }
            $modes = array_values(CSVImporter::$modes);
            if (! in_array($mode, $modes))
            {
                $this->error("Invalid import mode $mode!\nValid modes are: " . implode(', ', $modes));
                return;
            }

            $res = (new $importer)->import(Null, $this->argument('mode'));
        }
    }

    protected function getArguments()
    {
        return [
            [
                'model',
                InputArgument::REQUIRED, 'Specify which model to import. Valid models are: ' .
                implode(', ', array_merge(array_keys(CSVImporter::get_importers()), ['all'])), Null
            ],
            [
                'mode',
                InputArgument::OPTIONAL, 'Specifies the mode the importer runs in. ' .
                'Valid modes are: ' . implode(', ', array_values(CSVImporter::$modes)) . '.',
                CSVImporter::$modes['default']
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
            [
                'dbg', null, InputOption::VALUE_NONE,
                'If passed, shows additional output.'
            ],
        ];
    }
}
