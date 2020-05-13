<?php

return [
    'client' => [
        'hosts' =>  [
            [
                'host'       => env('ELASTICSEARCH_HOST', 'localhost'),
                'port'       => env('ELASTICSEARCH_PORT', 9200),
                'scheme'     => env('ELASTICSEARCH_SCHEME', null),
                'user'       => env('ELASTICSEARCH_USER', null),
                'pass'       => env('ELASTICSEARCH_PASS', null),

                // If you are connecting to an Elasticsearch instance on AWS, you will need these values as well
                'aws'        => env('AWS_ELASTICSEARCH_ENABLED', true),
                'aws_region' => env('AWS_DEFAULT_REGION', ''),
                'aws_key'    => env('AWS_ACCESS_KEY_ID', ''),
                'aws_secret' => env('AWS_SECRET_ACCESS_KEY', '')
            ],
        ]
    ],

    'update_mapping' => env('SCOUT_ELASTIC_UPDATE_MAPPING', true),
    'indexer' => env('SCOUT_ELASTIC_INDEXER', 'single'),
    'document_refresh' => env('SCOUT_ELASTIC_DOCUMENT_REFRESH'),
];
