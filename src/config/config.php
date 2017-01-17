<?php

return array(
    'import' => [
        'class_path' => '\\csv\\importers\\*Importer.php',
        'default_csv_path' => '/csv/files/',
    ],
    'export' => [
        'class_path' => '\\csv\\exporters\\*Exporter.php',
        'default_csv_path' => '/csv/files/',
    ],
);
