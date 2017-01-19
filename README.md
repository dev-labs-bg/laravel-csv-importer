# NOTE: This documentation is a work in progress. I've left some sections blank and will be filling them in gradually. Use at your own risk! 

---
# About Laravel CSV Importer


# Features
  - Automatic importer/exporter registration
  - CSV field preprocessing
  - CSV field validation
  - Rollback on error
  - Cross CSV references (relationships)
  - Automatic dependency resolution

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

The package is configued by default to look for:
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

# Configuration
The importer package comes preconfigured to look for CSV files and importer/exporter scripts in the `app/csv/files/` and `app/csv/importer` (and exporter) folders. If you wish to change this, you'll need to first get your own (application) copy of the configuration by running the following command:

```php artisan config:publish yavor-ivanov/csv-importer```

You will get a copy of the default configuration in `app/config/packages/yavor-ivanov/csv-importer/config.php`. Any changes to this file override the default configuration.

This is what the default config file looks like:
```
    'import' => [
        'class_path' => '\\csv\\importers\\*Importer.php',
        'class_match_pattern' => '/^(?!CSV)(.*)Importer$/',
        'default_csv_path' => '/csv/files/',
    ],
    'export' => [
        'class_path' => '\\csv\\exporters\\*Exporter.php',
        'class_match_pattern' => '/^(?!CSV)(.*)Exporter$/',
        'default_csv_path' => '/csv/files/',
    ],
```

The configuration options are ass follows:
  - `class_path` - The location from which to include php scripts for the importers/exporters (relative to the `app` falder)
  - `class_match_pattern` - The pattern by which the package distinguishes importers/exporters from other classes. This is used for the automatic importer/exporter registry.
  - `default_csv_path` - The default directory the importer/exporter looks for CSV files (relative to the `app` folder)

# Naming conventions
The package establishes the following file and class naming convention for the importer/exporters:

  - All importers/exporters must be placed in their designated folders (`app/csv/importers` and `app/cs/exporters` by default). This location is controlled by the `class_path` configuration option.
  - The PHP files themselves must end in `Importer.php` by default (`Exporter.php` for exporters). This is also controlled by the `class_path` configuration option.
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

The importer suports the following modes:
  - `append` - Only adds **new** data to the table. Does not delete records that have been removed from the CSV, nor does it update records that have been changed.
  - `overwrite` - Deletes everything in the table, and imports the CSV.
  - `update` - Some as append, but updates records in the table.
  - `validate` - Checks the CSV file for errors. Does not write to the database.

The `mode` parameter is optional. The importer always runs in `append` mode if one is not supplied.

**NOTE:** As of the moment, the package doesn't support deleting records from the database when removed from a CSV file, as this is potentially error prone. A `prune` mode will be added in later versions that exclusively performs this operation. Right now, the only way to delete database records is with the `overwrite` option.

For more information on the command format, run `php artisan help csv:import`

### CSV format

| id  | role_name   |
| --- | ----------- |
| 1   | super admin |
| 2   | admin       |
| 3   | moderator   |
| 4   | user        |

Raw csv:
```
id,role_name
1,"super admin"
2,admin
3,moderator
4,user
```

**NOTE:** Cells which include a space character must be quoted in the CSV output. This is a byproduct of the csv reader dependency of this package.


### Table format

| id  | role_name   | csv_id |
| --- | ----------- | ------ |
| 4   | super admin | 1      |
| 5   | admin       | 2      |
| 6   | moderator   | 3      |
| 7   | user        | 4      |

The table must include a `csv_id` column. This is used by the importer as a unique key when: referencing other CSV files, checking for existence of a record in the database, updating records.

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

The [`CSVReferenceTrait`](src/YavorIvanov/CsvImporter/CsvImporterServiceProvider.php) registers a `save` hook, which sets the proper `csv_id` for models saved without one.

**NOTE:** You **can** import to properties not listed in the `$fillable` array, as the importer turns off the Eloquent field guarding while importing. (Don't worry, it re-guards them when it's done.)

### Anatomy of an importer
```
<?php
use YavorIvanov\CsvImporter\CSVImporter;
class UserRolesImporter extends CSVImporter
{
    public $file = 'user_roles.csv';
    protected $model = 'UserRole';
    protected $cache_key = ['role_name' => 'role_name'];
    protected $primary_key = ['id' => 'csv_id'];

    protected function update($row, $o)
    {
        $o->csv_id = $row['id'];
        $o->role_name = $row['role_name'];
        $o->save();
    }

    protected function import_row($row)
    {
        return InstituteType::create([
            'role_name' => $row['role_name'],
            'csv_id' => $row['id'],
        ]);
    }
}
```

### Referencing an importer and adding dependencies
### Adding preprocessors
### Adding validation
### Import/Export events

# Contributing
# [License](/LICENSE)
