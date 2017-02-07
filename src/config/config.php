<?php

return array(
    'import' => [
        'file_match' => '*Importer.php',
        'register_path' => '\\csv\\importers\\',
        'class_match_pattern' => '/^(?!CSV)(.*)Importer$/',
        'default_csv_path' => '/csv/files/',
    ],
    'export' => [
        'file_match' => '*Exporter.php',
        'register_path' => '\\csv\\exporters\\',
        'class_match_pattern' => '/^(?!CSV)(.*)Exporter$/',
        'default_csv_path' => '/csv/files/',
        'backup_csv_path' => '/csv/files/backup/',
    ],
);
