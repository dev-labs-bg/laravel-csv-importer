<?php

return array(
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
);
