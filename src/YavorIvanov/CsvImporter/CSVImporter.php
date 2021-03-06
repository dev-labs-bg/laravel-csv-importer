<?php namespace YavorIvanov\CsvImporter;

use MJS\TopSort\Implementations\ArraySort;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use \DateTime;
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'include_dir.php');

function listify($val)
{
    if (! is_array($val))
        return [$val];
    return $val;
}

abstract class CSVImporter
{
    protected $column_mapping = [];
    private $processors = [];
    public $file = '';
    protected $model = '';

    protected $cache_key = [];
    protected static $deps = [];
    protected $context = [];
    protected $cache = Null;
    public static $modes = ['default'=>'append', 'overwrite', 'update', 'validate'];
    private $mode = '';

    // Primary key - used for intra-csv file relations.
    // The cache key is used if no primary key is defined.
    protected $primary_key = '';

    protected function import_row($row)
    {
        $o = new $this->model;
        return $this->fill_model_from_mappings($row, $o);
    }

    protected function update($row, $o)
    {
        return $this->fill_model_from_mappings($row, $o);
    }

    private function fill_model_from_mappings($row, $o)
    {
        foreach ($this->column_mapping as $p)
        {
            $col = $p;
            $model_prop = $p;
            if (is_array($p))
            {
                $col = key($p);
                $model_prop = current($p);
                if (is_array($model_prop))
                    $model_prop = $model_prop['name'];
            }
            try
            {
                eval('$o->' . "$model_prop" . ' = $row[$col]' . ';');
            }
            catch (ErrorException $e)
            {
                Log::error(
                    'Could not set ' . $p . ' for model ' . ucfirst(get_class($o)).
                    ' instance id ' . $o->id . ".\n" . 'CSV column: ' . $col
                );
                die;
            }
        }
        return $o;
    }

    // PHP doesn't allow lambdas in the main class body.
    protected function get_processors()
    {
        return [
            'integer' => function ($v) { return intval($v); },

            'to_datetime' => function ($v, $fmt='d/m/y H:i')
            {
                $created_at = DateTime::createFromFormat($fmt, $v);
                return $created_at->format('Y-m-d H:i:s');
            },

            'string_to_null' => function ($v)
            {
                if (strtolower($v) == 'null')
                    return Null;
                return $v;
            },
        ];
    }

    protected function get_validators()
    {
        return [
            'unique' => function ($col, $row)
            {
                $val = $row[$col];
                $current_obj = $this->get_from_cache($row);
                $model_col = array_get($this->column_mapping, "$col.name", $col);
                $occurences = $this->cache->reduce(function($carry, $o) use ($current_obj, $model_col, $val) {
                    if ($o != $current_obj && strtolower($o->$model_col) == strtolower($val))
                        return $carry + 1;
                    return $carry;
                }, 0);

                if ($occurences > 0)
                {
                    Log::error("A $this->name with $col = $val already exists in $this->file.");
                    die;
                }
            },
        ];
    }

    public static function get_importers()
    {
        // TODO <Yavor>: There's lots of duplicated code with the exporter here.
        //               Move this somewhere where both can reuse it.
        $path = Config::get('csv-importer::import.register_path');
        $match_file = (Config::get('csv-importer::import.file_match'));
        include_dir($path . $match_file);
        $importer_classes = array_filter(get_declared_classes(), function ($cls)
        {
            // Match all importers and exclude self.
            $match = preg_match(Config::get('csv-importer::import.class_match_pattern'), $cls);
            return $match && $cls != __CLASS__;
        });
        foreach ($importer_classes as $key=>$cls)
        {
            $name = $cls::importer_name($cls);
            $importer_classes[$name] = $cls;
            unset($importer_classes[$key]);
        }
        return $importer_classes;
    }

    public static function get_importer($name)
    {
        $importers = self::get_importers();
        if (array_key_exists($name, $importers))
            return $importers[$name];
        return Null;
    }

    public static function sort_importers($importers)
    {
        foreach ($importers as $name=>$cls)
            $importers[$name] = $cls::get_dependencies();
        $sorter = new ArraySort;
        $sorter->set($importers);
        $importers = $sorter->sort();
        return $importers;
    }

    protected function pivot_row($row)
    {
        return [$row];
    }

    protected function get_from_cache($val)
    {
        if (! $this->cache_key)
            return Null;
        if (is_array($val))
            $val = $val[key($this->cache_key)];
        $model_key = current($this->cache_key);
        return $this->cache->from_index($val);
    }

    protected function get_from_context($ctx, $key)
    {
        $key = strtolower($key);
        try
        {
            return $this->context[$ctx][$key];
        }
        catch (ErrorException $e)
        {
            echo "\n";
            Log::error(
                ucfirst($this->name) . ' importer failed to find a ' . $ctx . ' row with key: ' .
                $key .  '. Please make sure that this record exists.'
            );
            if ($this->mode == 'validate')
                return reset($this->context[$ctx]);
            die;
        }
    }

    public static function importer_name($cls)
    {
        preg_match(Config::get('csv-importer::import.class_match_pattern'), $cls, $groups);
        return strtolower($groups[1]);
    }

    protected function delete_records()
    {
        call_user_func_array([$this->model, 'whereNotNull'], ['id'])->delete();
    }

    public static function get_dependencies()
    {
        $cls = get_called_class();
        return $cls::$deps;
    }

    protected function process($processor, $params)
    {
        return call_user_func_array($this->processors[$processor], listify($params));
    }

    protected function validate($processor, $params)
    {
        return call_user_func_array($this->validators[$processor], listify($params));
    }

    function __construct()
    {
        // Derived classes inherit the base class validators/processors.
        $this->validators = array_merge(
            self::get_validators(),
            $this->get_validators()
        );
        $this->processors = array_merge(
            self::get_processors(),
            $this->get_processors()
        );

        $csv_path = Config::get('csv-importer::import.default_csv_path');
        $this->file = app_path() . $csv_path . $this->file;
        $this->name = self::importer_name(get_class($this));
        if (! $this->primary_key && $this->cache_key)
            $this->primary_key = $this->cache_key;
        $this->context[$this->name] = [];
    }

    public function parse($file=Null, $offset=0, $limit=Null)
    {
        Event::fire('import.parse.start');
        $file = $file ?: $this->file;
        $CSV = new \mnshankar\CSV\CSV();
        $rows = $CSV->fromFile($file)->toArray();
        $limit = $limit ?: count($rows);

        $rows = array_slice($rows, $offset, $limit);
        $res = [];
        foreach ($rows as $idx=>$row)
        {
            foreach ($this->pivot_row($row) as $row)
            {
                $row = $this->parse_row($row);
                $this->validate_row($row);
                array_push($res, $row);
            }
        }
        return $res;
        Event::fire('import.parse.end');
    }

    public function import($file=Null, $mode='append', $offset=0, $limit=Null)
    {
        DB::beginTransaction();
        $this->mode = $mode;

        if ($mode == 'update' && !method_exists($this, 'update'))
            throw new \BadMethodCallException("Importer $this->name (" .
                            get_called_class() .
                            ") called in update mode, but doesn't define an update function.");

        // Resolve dependencies.
        $dep2class = $this::get_importers();
        foreach ($this::get_dependencies() as $name)
        {
            $dep = new $dep2class[$name];
            $dep_import_mode = $mode;

            // Don't cascade overwrite.
            //
            // TODO <Yavor>: Add an option to override this.
            // Maybe have override-single and override-cascade modes?
            // Cascading override could just show the list of dependencies that would be overriden,
            // along with a strong warning about what that means about database cascade deletion, etc.
            if ($mode == 'overwrite')
                $dep_import_mode = 'append';
            $dep->import(Null, $dep_import_mode);
            $this->context = array_merge($this->context, $dep->context);
        }

        if ($mode == 'overwrite')
            $this->delete_records();

        if ($this->model && $this->cache_key)
        {
            $this->cache = IndexedCollection::makeFromCollection(call_user_func([$this->model, 'all']))
                                ->case_insensitive_index(current($this->cache_key));

            if ($this->primary_key)
            {
                foreach ($this->cache as $o)
                {
                    $primary_key = $this->primary_key;
                    if (is_array($primary_key))
                        $primary_key = reset($primary_key);
                    $this->context[$this->name][strtolower($o->$primary_key)] = $o;
                }
            }
        }

        $rows = $this->parse($file, $offset, $limit);
        Event::fire('import.start', array(count($rows), $this->name));
        foreach ($rows as $row)
        {
            $o = Null;
            $o = $this->get_from_cache($row);

            if ($mode == 'update' && $o)
            {
                $o = $this->update($row, $o);
                if ($o->isDirty())
                    $o->save();
            }

            if (! $o)
            {
                Eloquent::unguard();
                $o = $this->import_row($row);
                if (! $o->exists())
                    $o->save();
                Eloquent::reguard();
            }

            if ($this->cache != Null)
                $this->cache->index($o, current($this->cache_key));

            if ($this->primary_key)
                $this->context[$this->name][strtolower($row[key($this->primary_key)])] = $o;
            Event::fire('import.update.progress');
        }
        if ($mode != 'validate')
            DB::commit();
        else
            DB::rollback();
        Event::fire('import.complete');
    }

    protected function parse_row($row)
    {
        // NOTE <Yavor>: CSV cells with no values for a column get removed from the
        // $row array, instead of defaulting to a NULL value, as NULL may be
        // a legitimate value.
        //
        // FIXME <Yavor>: As of the moment, there is no way to default missing cells to any
        // value via processors, as they are eliminated before the preprocessing step.
        // For now, you'll need to manually check if the column exists in the
        // $row array in the import/update.
        $cols_values  = array_filter($row);
        $column_processors = array_where($this->column_mapping, function($k, $v)
        {
            if (is_array($v))
                return array_key_exists('processors', $v);
            return false;
        });
        foreach ($column_processors as $k=>$v)
            $column_processors[$k] = $v['processors'];
        foreach ($column_processors as $name=>$processors)
        {
            $value = trim($row[$name]);
            if (strlen($value) == 0)
                $value = Null;

            $processors = listify($processors);

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
                array_unshift($params, $value);
                $value = $this->process($proc_name, $params);
            }
            $cols_values[$name] = $value;
        }
        return $cols_values;
    }

    protected function validate_row($row)
    {
        // NOTE <Yavor>: CSV cells with no values for a column get removed from the
        // $row array, instead of defaulting to a NULL value, as NULL may be
        // a legitimate value.
        $cols_values  = array_filter($row);
        $column_validators = array_where($this->column_mapping, function($k, $v)
        {
            if (is_array($v))
                return array_key_exists('validators', $v);
            return false;
        });
        foreach ($column_validators as $k=>$v)
            $column_validators[$k] = $v['validators'];
        foreach ($column_validators as $name=>$validators)
        {
            $value = $row[$name];
            $validators = listify($validators);

            foreach ($validators as $validator_name)
            {
                $this->validate($validator_name, [$name, $row]);
            }
        }
    }
}

?>
