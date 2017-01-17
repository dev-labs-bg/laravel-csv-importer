<?php namespace YavorIvanov\CsvImporter;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'include_dir.php');

abstract class CSVExporter
{
    public $file = '';
    protected $model = '';
    protected $column_mapping = [];
    protected $processors = [];


    // PHP doesn't allow lambdas in the main class body.
    protected function get_processors()
    {
        return [
            'null_to_string' => function ($v)
            {
                if ($v == Null)
                    return 'NULL';
                return $v;
            },

            'null_to_zero' => function ($v)
            {
                if ($v == Null)
                    return 0;
                return $v;
            },
        ];
    }

    protected function generate_row($o)
    {
        $row = [];
        foreach ($this->column_mapping as $p)
        {
            $col = $p;
            if (is_array($p))
            {
                $col = current($p);
                if (is_array($col))
                    $col = key($col);
                $p = key($p);
            }
            try
            {
                eval('$v = $o->' . "$p" . ';');
            }
            catch (ErrorException $e)
            {
                Log::error(
                    'Could not select ' . $p . ' for model ' . ucfirst(get_class($o)) . ' instance id ' . $o->id . '.'
                );
                die;
            }
            $row[$col] = $v;
        }
        return $row;
    }

    public static function exporter_name($cls)
    {
        return strtolower(str_replace('Exporter', '', $cls));
    }

    public static function get_exporters()
    {
        include_dir(Config::get('csv-importer::export.class_path'));
        $exporter_classes = array_filter(get_declared_classes(), function ($cls)
        {
            // Remove self from list.
            return preg_match('/^(?!CSV)(.*)Exporter$/', $cls);
        });
        foreach ($exporter_classes as $key=>$cls)
        {
            $name = $cls::exporter_name($cls);
            $exporter_classes[$name] = $cls;
            unset($exporter_classes[$key]);
        }
        return $exporter_classes;
    }

    public static function get_exporter($name)
    {
        $exporters = self::get_exporters();
        if (array_key_exists($name, $exporters))
            return $exporters[$name];
        return Null;
    }

    function __construct()
    {
        $this->processors = array_merge(
            self::get_processors(),
            $this->get_processors()
        );
        $csv_path = Config::get('csv-importer::import.default_csv_path');
        $this->file = app_path() . $csv_path . $this->file;
        $this->name = self::exporter_name(get_class($this));
    }

    public function export($file=Null)
    {
        DB::transaction(function() use ($file)
        {
            $collection = Null;
            if (method_exists($this, 'model'))
                $collection  = $this->model();
            else
                $collection  = call_user_func([$this->model, 'all']);

            $csv = [];
            foreach ($collection as $o)
            {
                $row = $this->generate_row($o);
                $row = $this->postprocess_row($row);
                array_push($csv, $row);
            }
            $max_row_length = max(array_map('count', $csv));
            foreach ($csv as $k=>$row)
                $csv[$k] = array_pad($row, $max_row_length, '');
            $path = $this->file;
            $CSV = new \mnshankar\CSV\CSV();
            $CSV->with($csv)->put($path);
        });
    }

    protected function process($processor, $params)
    {
        return call_user_func_array($this->processors[$processor], listify($params));
    }

    protected function postprocess_row($row)
    {
        // Remove empty cells
        $cols_values  = $row;
        foreach ($this->column_mapping as $col)
        {
            $csv_col = $db_col = Null;
            if (! is_array($col))
                continue;

            $db_col = key($col);
            $csv_col = current($col);
            if (! is_array($csv_col))
                continue;

            $processors = listify(current($csv_col));
            $csv_col = key($csv_col);

            // Format the processors in (name => params) format,
            // as not all processors have extra parameters defined.
            foreach ($processors as $proc_name => $params)
            {
                if (! array_key_exists($proc_name, $this->processors))
                {
                    $processors[$params] = [];
                    unset($processors[$proc_name]);
                }
                else
                {
                    $processors[$proc_name] = listify($processors[$proc_name]);
                }
            }

            foreach ($processors as $proc_name => $params)
            {
                array_unshift($params, $row[$csv_col]);
                $value = $this->process($proc_name, $params);
            }
            $cols_values[$csv_col] = $value;
        }
        return $cols_values;
    }
}

?>
