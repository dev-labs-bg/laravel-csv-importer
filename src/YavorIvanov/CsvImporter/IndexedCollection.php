<?php namespace YavorIvanov\CsvImporter;

class IndexedCollection extends \Illuminate\Database\Eloquent\Collection
{
    public static function makeFromCollection($o)
    {
        return new static($o->items);
    }

    public function case_insensitive_index($keyBy)
    {
        $results = [];
        foreach ($this->items as $item)
        {
            $key = data_get($item, $keyBy);
            $results[strtolower($key)] = $item;
        }
        return new static($results);
    }

    public function index($o, $key=NULL)
    {
        if (! $key)
            $key = $o->getKey();

        $this->items[strtolower($o->$key)] = $o;
        return $this;
    }

    public function from_index($val)
    {
        if (! array_key_exists(strtolower($val), $this->items))
            return Null;
        return $this->items[strtolower($val)];
    }
}
