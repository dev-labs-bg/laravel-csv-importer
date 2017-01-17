<?php namespace YavorIvanov\CsvImporter;
use Illuminate\Support\Facades\Schema;

trait CSVReferenceTrait
{
    public static function bootCSVReferenceTrait()
    {
        // Auto-increments the csv_id index if none is given.
        static::saving(function($model)
        {
            $cols = Schema::getColumnListing($model->getTable());
            if (in_array('csv_id', $cols))
            {
                if (! $model->csv_id)
                    $model->csv_id = $model->max('csv_id') + 1;
            }
        });
    }
}
