# About Laravel CSV Importer
A Laravel 4 library for importing/exporting CSV files into/out of database tables using Eloquent.

Note: This library is still early in development. You may find functionality which is inflexible, altogether, poorly documented. You are welcome to open issues, but my schedule may prevent me from reacting to them in a timely fashion. Nevertheless, I've open sourced it, as none of the Laravel CSV import/export packages could handle cross-CSV references.

# Features
  - Automatic importer/exporter registration
  - CSV field preprocessing
  - CSV field validation
  - Cross CSV references (relationships)
  - Automatic dependency resolution
  - Rollback on error

# Requirements
  - PHP >=5.4
  - Laravel Framework >= 4
  - Composer
  - RDBMS supported by Eloquent (Laravel ORM)

# Installation

To install, run: ```php composer.phar require yavor-ivanov/csv-importer``` in your laravel project root.

Alternatively, you can manually add ```"yavor-ivanov/csv-importer": "dev-master"``` to your `composer.json` under the `require` field.

Then, open `app/config/app.php` and add the following line in the `providers` array:

```
'providers' => array(
    'YavorIvanov\CsvImporter\CsvImporterServiceProvider',
)
```

The package is configured by default to look for:
  - CSV files in `app/csv/files`
  - Importers in `app/csv/importers/`
  - Exporters in `app/csv/exporters/`
  - Place CSV backups in `app/csv/files/backup/`

You'll need to create all these folders by running these commands:
```
mkdir -p app/csv/files/backup
mkdir app/csv/importers
mkdir app/csv/exporters
```

# Examples
I've set up an [examples repository](https://github.com/dev-labs-bg/laravel-csv-importer-examples) containing a Laravel 4 application with multiple importers and exporters.

# Configuration
The importer package comes preconfigured to look for CSV files and importer/exporter scripts in the `app/csv/files/` and `app/csv/importer` (and exporter) folders. If you wish to change this, you'll need to first get your own (application) copy of the configuration by running the following command:

```php artisan config:publish yavor-ivanov/csv-importer```

You will get a copy of the default configuration in `app/config/packages/yavor-ivanov/csv-importer/config.php`. Any changes to this file override the default configuration.

This is what the default config file looks like:
```
    'import' => [
        'file_match' => '*Importer.php',
        'register_path' => '\\csv\\importers\\',
        'class_match_pattern' => '/^(?!CSV)(.*)Importer$/',
        'default_csv_path' => '/csv/files/',
    ],
    'export' => [
        'file_match' = '*Exporter.php',
        'register_path' => '\\csv\\exporters\\',
        'class_match_pattern' => '/^(?!CSV)(.*)Exporter$/',
        'default_csv_path' => '/csv/files/',
    ],
```

The configuration options are ass follows:
  - `file_match` - A regular expression that matches the file names to be considered importers/exporters. The package uses this to auto-register your importer/exporter classes.
  - `register_path` - The location from which to include php scripts for the importers/exporters (relative to the `app` folder)
  - `class_match_pattern` - The pattern by which the package distinguishes importers/exporters from other classes. This is used for the automatic importer/exporter registry.
  - `default_csv_path` - The default directory the importer/exporter looks for CSV files (relative to the `app` folder)

# Naming conventions
The package establishes the following file and class naming convention for the importer/exporters:

  - All importers/exporters must be placed in their designated folders (`app/csv/importers` and `app/cs/exporters` by default). This location is controlled by the `register_path` configuration option.
  - The PHP files themselves must end in `Importer.php` by default (`Exporter.php` for exporters). This is also controlled by the `file_match` configuration option.
  - The classes in the files must match the `class_match_pattern`. Ending the class name with `Importer` or `Exporter` is enough for a valid class name by default.

In exchange for these limitation, the package is able to automatically register any importers/exporters that follow the convention. This allows you to:
  - Create importer/exporter scripts and immediately use them in import/export commands
  - Reference importers/exporters as dependencies of other importers/exporters and have the package automatically resolve them at runtime.
  - Obtain a list of all registered importers/exporters.

# Usage
### Commands
The package comes with two commands `csv:import` and `csv:export`

The command format is: ```csv:import <importer_name> [<mode>]```

Example usage: ```php artisan csv:import categories``` or ```php artisan csv:import expenses validate```

The importer supports the following modes:
  - `append` - Only adds **new** data to the table. Does not delete records that have been removed from the CSV, nor does it update records that have been changed.
  - `overwrite` - Deletes everything in the table, and imports the CSV.
  Note: Overwrite mode does not propagate to dependencies, as this may result in a cascading delete of the whole database. As of this moment there is no way to override this behaviour.
  - `update` - Some as append, but updates records in the table.
  - `validate` - Checks the CSV file for errors. Does not write to the database.

The `mode` parameter is optional. The importer always runs in `append` mode if one is not supplied.

**NOTE:** As of the moment, the package doesn't support deleting records from the database when removed from a CSV file, as this is potentially error prone. A `prune` mode will be added in later versions that exclusively performs this operation. Right now, the only way to delete database records is with the `overwrite` option.

For more information on the command format, run `php artisan help csv:import`

### CSV format

The only requirements for the CSV format are:
1. The CSV must include a header row
2. All columns in the header must be named
3. There must be at least one unique column
4. Cells which contain spaces must be quoted in the CSV output

An example of a valid CSV:
```
id,role_name
1,"super admin"
2,admin
3,moderator
4,user
```

| id  | role_name   |
| --- | ----------- |
| 1   | super admin |
| 2   | admin       |
| 3   | moderator   |
| 4   | user        |
*(Table view)*


### Database table format

| id  | role_name   | csv_id |
| --- | ----------- | ------ |
| 4   | super admin | 1      |
| 5   | admin       | 2      |
| 6   | moderator   | 3      |
| 7   | user        | 4      |

In order to make use of caching, update mode, and cross-CSV references, the database table must include a `csv_id` column. This is used to find the database record for the corresponding CSV row.

### Model
The package uses Laravel's Eloquent ORM to read and write to the database. This means that there is no configuration needed for database access. This also allows you to use Eloquent features (such as observers, validators, custom properties, etc.) while importing and exporting.

In order to make sure the `csv_id` column autoincrements when creating new records outside the importer, you must use the `CSVReferenceTrait` in your model like so:

```
class UserRole extends Eloquent
{
    use YavorIvanov\CsvImporter\CSVReferenceTrait;

    protected $fillable = ['role_name'];
    protected $table = 'user_roles';

    // ...
}
```

The [`CSVReferenceTrait`](src/YavorIvanov/CsvImporter/CSVReferenceTrait.php) registers a `save` hook, which sets the proper `csv_id` for models saved without one.

**NOTE:** You **can** import to properties not listed in the `$fillable` array, as the importer turns off the Eloquent field guarding while importing. (Don't worry, it re-guards them when it's done.)

# Importers
### Minimum configuration importer example
The following is the minimum configuration needed to create an importer class, which creates a collection of `UserRole` models and imports them to the database, and supports `update` mode:

```
<?php
use YavorIvanov\CsvImporter\CSVImporter;
class UserRolesImporter extends CSVImporter
{
    // Defualt name for the CSV to import.
    public $file = 'user_roles.csv';

    // Eloquent model name to create/update (case sensitive)
    protected $model = 'UserRole';

    // Maps an id field in the csv to a database id field. Format ['csv_column_name' => 'db_column_name']
    protected $primary_key = ['id' => 'csv_id'];

    // Maps an id field in the csv to a database id field. Format ['csv_column_name' => 'db_column_name']
    protected $cache_key = ['id' => 'csv_id'];

    protected function update($row, $o)
    {
        $o->csv_id = $row['id'];
        $o->role_name = $row['role_name'];
        $o->save();
    }

    protected function import_row($row)
    {
        return UserRole::create([
            'role_name' => $row['role_name'],
            'csv_id' => $row['id'],
        ]);
    }
}
```
Every importer name must end with `Importer` (controlled by the [`class_match_pattern`](https://github.com/dev-labs-bg/laravel-csv-importer#configuration) property) and extend the `CSVImporter` base class.

Field breakdown:
  - `$file` - The name of the file to load from `app/csv/files/` (csv folder configurable)
  - `$model` - The package uses the Eloquent ORM to load and save from/to the database. In order to call select and save functions, the package needs to know the model the importer corresponds to.
  - `$cache_key` - The package caches entities already in the database as well as entities being imported in order to decrease the amount of database queries for self-referencing CSV files, update mode imports, as well as skip duplicate imports. In order to do that, the package needs to know the mapping between the primary unique column of the CSV and the database table.
  - `$primary_key` - All importers share a `context`, from which you may retrieve entities of a dependency importer. This works exactly as foreign keys do, i.e.: CSV `A` references a row from CSV `B` by some unique column. The `$primary_key` is the mapping between that (CSV) unique column and a database table unique column.
  By default, the CSV id column is named `id`, while the table column is `csv_id`.

  **Note:** The importer cannot use the (more or less default) `id ` column in the table for comparison, because id collisions may occur when appending to a non-empty table.


  - `import_row` - Once the importer diffs the database table and CSV it iterates over the **new** rows in the CSV file. For every new row found, the `import_row` function is called. The base importer passes the current row in the `$row` parameter, and expects an Eloquent model to be returned.

  **Note:** At the moment, importer does not support conditional importing of rows. The `import_row` function must return a model instance.
  - `update` - This function is called by the base importer for every record found in **both** the database table and the CSV. The importer passes the current CSV row in the `$row` parameter, as well as the Eloquent model from the database in the `$o` parameter.

  **Note:** Currently, the importer doesn't check for actual changes to the model. **It will always call the function**. Because of this current limitation, you must manually call `save()` on your models.

  **Note:** This function is only every called when running the importer in `update` mode.

### Column mappings
Often, the database table columns differ in name (or representation) from the CSV files you wish to import. For example, databases accept dates in the [ISO 8601 format](https://en.wikipedia.org/wiki/ISO_8601) (`YYY-MM-DD`), but your CSV files may contain dates in the American date format (`MM/DD/YYYY`). The `$column_mappings` property allow you to define any name difference between CSV and database table, as well as transform and validate the CSV data before saving.

The `$column_mappings` format is flexible. It allows you to define a column with a name difference, multiple preprocessing steps, as well as validation functions:
```
protected $column_mappings = [
    'csv_column' => ['name' => 'table_column',
                          'processors' => ['processor_name' => 'parameter'],
                          'validators' => ['validator_name' => 'parameter']
                ],
];
```

When the importer reads the CSV file, it looks at the `$column_mappings` to determine if it should transform the input data (or run validations against it).

The processor function are read from the result of the `get_processors` function:
```
protected function get_processors()
{
    return [
            'integer' => function ($v) { return intval($v); },

            'to_datetime' => function ($v, $fmt='d/m/y H:i')
            {
                $created_at = DateTime::createFromFormat($fmt, $v);
                return $created_at->format('Y-m-d H:i:s');
            },
    ];
}
```

The function names of the preprocessors are determined by the array keys returned.

The validators are returned by the `get_validators` function.

For brevity, you can omit unused features from your column specification. For example, importing a column with only a name change can be condensed to the following:
```
protected $column_mappings = [
    'csv_column' => 'table_column',
];
```

Another example of this is using preprocessors without passing in parameters:
```
protected $column_mappings = [
    'csv_column' => ['name' => 'table_column',
                    'processors' => ['processor1', 'processor2'],
                ],
];
```

Or omitting the array if there is only one processor:
```
protected $column_mappings = [
    'csv_column' => ['name' => 'table_column',
                    'processors' => 'my_column_processor',
                ],
];
```

### Adding dependencies and referencing an importer
Often times, the data in CSV files wants to be relational in nature. In order to resolve the relationships properly, the importer needs to import files in the correct order. For instance, if the `users.csv` makes a reference to a phone number from `phone_numbers.csv`, the importer should make sure to import the phone numbers before it imports the users, as well as fetch any phone numbers that may be in the database (but not in the `phone_numbers.csv` file).

The package evaluates the import order by reading the dependencies of each importer defined in the static `$deps` property. This collection of dependencies forms a dependency graph that can be [topologically sorted](https://en.wikipedia.org/wiki/Topological_sorting) to yield order in which the package must call the importers.

Whenever you run an importer that has dependencies from the command line, you will see progress bars for the importer **and** its dependencies:
```
Importing: Book.
 6/6 [============================] 100%
 7/7 [============================] 100%
```
*Here, the `Book` importer depends on the `Author` importer. As of the moment, the progress bars are not labeled.*

Aside from declaring dependencies, an importer will need to access the data from the entities of its dependencies. In the Books and Authors example, a Books importer may wish to find its Author's id and use it as a foreign key.

The `get_from_context` function returns an Eloquent model instance from a dependency name and a search value:
`protected function get_from_context($ctx, $key)`

The column on which to select on is determined by the `$primary_key` property of the dependent importer. If no `$primary_key` is defined, the `$cache_key` mapping is used instead. This allows you to cache a model by one column (most frequently `id`), yet refer to  if by another (unique) column from other importers. An example of this is caching a User model by `id`, but referring to it by `email` from an Order.

### Caching
In the beginning of the import, the package selects all rows from the importer's `$model` table, and caches them by a unique CSV column in the `$cache_key` mapping:

`protected $cache_key = ['table_column_name' => 'csv_column_name'];`

This allows the package to avoid importing CSV rows that are already in the database. Each CSV record can be checked for inclusion in the database by comparing the values of the unique keys defined in the `$cache_key` mapping. For example, a mapping of `id` <--> `csv_id` would search the cache for an Eloquent entity with an `id` equal to the current CSV row's `csv_id`.

Aside from the performance benefit of caching, you can query the cache in importers by calling the 'get_from_cache($hash)'. This is useful when importing self-referential CSVs. An example of this would be importing a tree structure in following format: `[id, name, parent_id]`, where each branch prepends its parent's name to its own.

### Adding preprocessor functions
You can define processor functions for your importers by adding a `get_processors()` function, which returns an array of functions. The package will run these functions on the columns which list these processors in the the `$column_mappings`.

You can define the processor functions inline:
```
protected function get_processors()
{
    return [
        'null_or_datetime' => function ($v, $fmt='Y-m-d')
        {
            $v = $this->process('string_to_null', $v);
            if ($v == Null)
                return $v;
            return $this->process('to_datetime', [$v, $fmt]);
        },
    ];
}
```

Or, if you wish to share them across importers, you can define them in a shared file, and reference them:

```
protected function get_processors()
{
    return [
        'null_or_datetime' => my_datetime_function
    ];
}
```

The [base importer](https://github.com/Yavor-Ivanov/laravel-csv-importer/blob/master/src/YavorIvanov/CsvImporter/CSVImporter.php#L39) also defines its own processors that can be used by all importers, [as they are 'inherited'](https://github.com/Yavor-Ivanov/laravel-csv-importer/blob/master/src/YavorIvanov/CsvImporter/CSVImporter.php#L189).

**Note:** As of the moment there is no way to register global preprocessors like the base importer does.

### Adding validation functions
Validation functions are defined similarly to the preprocessors. The `get_validators` function of the importer returns an array of functions that can be defined to run when an importer runs via the `$column_mappings` property.

Below is an example validator which checks a column for uniqueness:
```
protected function get_validators()
{
    return [
        'unique' => function ($col, $row)
        {
            $val = $row[$col];
            $current_obj = $this->get_from_cache($row);
            $model_col = array_get($this->column_mappings, "$col.name", $col);
            $occurrences = $this->cache->reduce(function($carry, $o) use ($current_obj, $model_col, $val) {
                if ($o != $current_obj && strtolower($o->$model_col) == strtolower($val))
                    return $carry + 1;
                return $carry;
            }, 0);

            if ($occurrences > 0)
            {
                Log::error("A $this->name with $col = $val already exists in $this->file.");
                die;
            }
        },
    ];
}
```

The validator functions receive both the column name they have been called on and the whole CSV row. This allows you to create validations rules such as: `property X is valid only if Y is NULL`.

### Column pivoting
Sometimes your pivot CSV table nicely mirrors the database pivot table:

| book_id |  genre_id |
|---------|-----------|
| 1       | 2         |
| 1       | 1         |

Other times, your many to many pivot CSV may come in a weird, multiple column format:

| book_id |  genre1 |  genre2 |  ... |
|---------|---------|---------|------|
| 1       | 2       |  1      |      |

Although changing the CSV format to be in tune with the database table layout would be ideal, you may not always have that luxury (widely used legacy formats, for instance).

When this happens, you can do some preprocessing of the individual rows before the import/update function reads them.

In the example below, the `pivot_row` function takes a row in the `[book_id, genre1, genre2, ... genreN]` format and replaces it with multiple rows of `[book_id, genre_id]` tuples:
```
protected function pivot_row($row)
{
    $pivoted_row = [];
    $book_id = $row['book'];

    // Loops over the genre columns only, as there is only one book column.
    foreach (array_filter(array_slice($row, 1)) as $genre_id)
    {
        array_push($pivoted_row, [
            'book_id'  => $book_id,
            'genre_id' => $genre_id,
        ]);
    }
    return $pivoted_row;
}
```

The pivot step is run before column processors and validators.

# Exporters
### Minimum configuration exporter example

The following is a minimal exporter example using the `$column_mapping` to drive the CSV export:

```
<?php
use YavorIvanov\CsvImporter\CSVExporter;
class UserRolesImporter extends CSVExporter
{
    // Defualt name for the CSV to export.
    public $file = 'user_roles.csv';

    // Eloquent model to select from (case sensitive)
    protected $model = 'UserRole';

    protected $column_mapping = [
        'csv_id' => 'id',
        'role_name',
    ];
}
```

**Note:** Every exporter class name must end with Exporter (controlled by the [`class_match_pattern`](https://github.com/dev-labs-bg/laravel-csv-importer#configuration) property) and extend the CSVExporter base class.

### Field breakdown:

- `$file` - The name of the file to save to. The default path to file is `app/csv/files/` (csv folder configurable)
- `$model` - The package uses the Eloquent ORM to read data from the database. In order to select records, the package needs to know the model the exporter corresponds to.

### Column mappings

The exporter `$column_mappings` property serves the same purpose as the importer [`$column_mappings`](https://github.com/dev-labs-bg/laravel-csv-importer/#column-mappings). It allows you to define a relationship between the table columns (or model properties/methods) and CSV columns. The exporter uses this mapping to generate the CSV output.

Unlike the importer, the exporter `$column_mappings` allow you to use model properties, as well as map model functions to CSV columns. Here's an example of a mappnig between a model property and a CSV column that uses a postprocessing function:

```
protected $column_mappings = [
    'model_property' => ['name' => 'csv_column', 'processors' => ['postprocessor_name' => 'parameter']],
];
```

And an example of an exporter mapping a model function to a CSV column:

```
protected $column_mappings = [
    'compute_property()' => ['name' => 'csv_column_name', 'processors' => ['postprocessor_name' => 'parameter']],
];
```

Exporting is generally more straightforward than importing, so there's no need to use an `export_row` like function [(although the option is available)](https://github.com/dev-labs-bg/laravel-csv-importer/#generating-rows-programatically). The exporter can read the `$column_mappings` and automatically outputs the CSV file.

In order to support certain mappings, the exporter evaluates the key of the `$column_mappings` entry (`model_property` in the above example) and uses the result as the value of `csv_column` when exporting. Some examples of such mappings are: computed properties, aggregate functions, and relationship properties.

The [book exporter example](https://github.com/dev-labs-bg/laravel-csv-importer-examples/blob/master/app/csv/exporters/BookExporter.php#L35) uses such a mapping to get the csv id of its `authors` relationship:

```
    protected $column_mapping = [
        ['authors()->first()->csv_id' => 'author'],
        // ...
    ];
```

**Note:** The exporter uses the `$column_mapping` key as part of an [`eval()`](https://github.com/dev-labs-bg/laravel-csv-importer/blob/master/src/YavorIvanov/CsvImporter/CSVExporter.php#L51) call. The call is limited to the current model instance, **yet there are no checks for malicious intent**, such as calling `delete()` or using `id; call_malicious_function()` as a key.

Like the importer `$column_mapping` property, the exporter allows you to simplify the row declaration if you don't need to use a postprocessor, or the CSV and database columns coincide:

```
protected $column_mapping = [
    'name',                                                                                      // Column name in the CSV and database is the same
    ['table_column' => 'csv_column'],                                                            // Table column to CSV column mapping with no postprocessor
    ['table_column' => ['csv_column' => ['processor_name']]],                                    // Post-processor without paramers (use defaults).
    ['table_column' => ['name' => 'csv_column', 'processors' => ['processor_name' => 'param']]], // Post-processor with parameters.
    ['table_column' => ['name' => csv_column', 'processors' => [
        'processor1' => ['param1', 'param2'],
        'processor2' => 'param',
        'processor3']
    ]]],                                                                                         // Multiple post-processors with a differing number of parameters.
];
```

### Post-processing

Like the importer, the package reads post-processor functions from the result of the `get_processors` function:

```
protected function get_processors()
{
    return [
            'null_to_zero' => function ($v)
            {
                if ($v == Null)
                    return 0;
                return $v;
            },
    ];
}
```
The function names of the post-processors are determined by the array keys returned from `get_processors`.

### Generating rows programatically

Sometimes, the column mappings just aren't flexible enough to handle your export logic. In such cases, you can use the `generate_row` function to generate the rows programatically. Cases where you may want to do this include: [exporting CSV files with a variable number of columns](https://github.com/dev-labs-bg/laravel-csv-importer-examples/blob/master/app/csv/exporters/BookGenreExporter.php#L58), exporting CSVs with data from multiple models, exporting data external to the model, etc.

Once the export process is started, the exporter selects all records from the `$model` entity, and calls `generate_row` on each record. The function should return an array in the following format: `['csv_column1' => 'value', 'csv_column2' => 'other_vaule']`.

The following is an example of exporting a CSV with a variable number of columns. [You can see the full code in the examples](https://github.com/dev-labs-bg/laravel-csv-importer-examples/blob/master/app/csv/exporters/BookGenreExporter.php#L58):

```
protected function generate_row($o)
{
    $row = parent::generate_row($o);
    $heading = 'genre';
    $current = 1;
    foreach ($o->genres as $genre)
    {
        $col_name = $heading . $current;
        $current += 1;
        $row[$col_name] = $genre->csv_id;
    }
    return $row;
}
```

**Note:** If you choose to override this function, the exporter will not process the `$column_mapping` property, unless you call `parent::generate_row()`. This allows you to mix custom row generation logic with column mappings if you want (or skip the automatic mapping entirely).

# Licensed under the MIT license
The project license file can be found [here](/LICENSE).
