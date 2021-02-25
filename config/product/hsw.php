<?php

return [

    /*
    |---------------------------------------------------------------------------
    | GENERAL
    |---------------------------------------------------------------------------
    */

    'app_name' => 'BollyFame',

    // Test Customers
    'test_customers' => [
        'shrikant.tiwari@bollyfame.com',
        'sanjay.sahu@bollyfame.com', 'sanjay.id7@gmail.com',
        'saurabh@bollyfame.com', 'saurabhk86@gmail.com',
        'ashwini.mhavarkar@bollyfame.com',
        'rohit.desai@bollyfame.com',
    ],

    /*
    |-----------------------------------------------------------------------
    | Mail
    |-----------------------------------------------------------------------
    */

    // SES -- Simple Email Service
    'mail' => [
        'driver' => env('MAIL_PEPIPOST_DRIVER', 'smtp'),
        'host' => env('MAIL_PEPIPOST_HOST', 'smtp.pepipost.com'),
        'port' => env('MAIL_PEPIPOST_PORT', 587),
        'encryption' => env('MAIL_PEPIPOST_ENCRYPTION', 'tls'),
        'username' => env('MAIL_PEPIPOST_USERNAME', 'sanjaysahu'),
        'password' => env('MAIL_PEPIPOST_PASSWORD', 'Sanjay@123$%^'),
        'sendmail' => '/usr/sbin/sendmail -bs',
        'pretend' => false,
        'from' => array('address' => 'support@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'from_for_transaction' => array('address' => 'noreply@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'from_for_support' => array('address' => 'support@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'bcc_email_ids' => [], // 'info@bollyfame.com'
        'bcc_for_transaction' => ['accounts@bollyfame.com'],
        'bcc_forgot_password' => [],
    ],

    // END Mail

    // pepipost -- https://pepipost.com/
    'mail_pepipost' => [
        'driver' => env('MAIL_PEPIPOST_DRIVER', 'smtp'),
        'host' => env('MAIL_PEPIPOST_HOST', 'smtp.pepipost.com'),
        'port' => env('MAIL_PEPIPOST_PORT', 587),
        'encryption' => env('MAIL_PEPIPOST_ENCRYPTION', 'tls'),
        'username' => env('MAIL_PEPIPOST_USERNAME', 'sanjaysahu'),
        'password' => env('MAIL_PEPIPOST_PASSWORD', 'Sanjay@123$%^'),
        'sendmail' => '/usr/sbin/sendmail -bs',
        'pretend' => false,
        'from' => array('address' => 'support@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'from_for_transaction' => array('address' => 'noreply@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'from_for_support' => array('address' => 'support@bollyfame.com', 'name' => 'BollyFame Digital Entertainment'),
        'bcc_email_ids' => [], // 'info@bollyfame.com'
        'bcc_for_transaction' => ['accounts@bollyfame.com'],
        'bcc_forgot_password' => [],
    ],
    // END Mail


    /*
    |-----------------------------------------------------------------------
    | Cache
    |-----------------------------------------------------------------------
    */

    'cache' => [
        'aws_elastic_cache_cluster_endpoint' => env('AWS_ELASTIC_CACHE_CLUSTER'),
    ],
    // END cache

    /*
    |-----------------------------------------------------------------------
    | S3
    |-----------------------------------------------------------------------
    */

    's3' => [
        /*
        |-------------------------------------------------------------------
        | S3 Access Keys
        |-------------------------------------------------------------------
        */

        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_S3_REGION', 'ap-south-1'),
        'acl' => env('AWS_S3_ACL', 'private'),
        'bucket' => env('AWS_S3_DEFUALT_BUCKET', 'bfmediaimages'),

        /*
        |-------------------------------------------------------------------
        | S3 Access Urls Used By Browsers
        |-------------------------------------------------------------------
        */

        'cloudfront_image_base_url' => 'https://d3oxidb1kcxtza.cloudfront.net/',
        'cloudfront_video_base_url' => 'https://d1g9r7lgdp6q07.cloudfront.ne/',
        'cloudfront_audio_base_url' => 'https://dfqwvgp874nmb.cloudfront.net/',

        'rawvideos' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bfmediarawvideos',
        ],

        'videos' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bfmediavideos',
        ],

        'rawaudios' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'hsmediarawaudios',
        ],

        'audios' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'hsmediaaudios',
        ],

        'rawimages' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'hsmediarawimages',
        ],

        'images' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bfmediaimages',
        ],

        'base_urls' => [
            'audio' => 'https://s3-ap-south-1.amazonaws.com/hsmediaaudios/',
            'raw_audio' => 'https://s3-ap-south-1.amazonaws.com/hsmediarawimages/',
            'photo' => 'https://s3-ap-south-1.amazonaws.com/bfmediaimages/',
            'raw_photo' => 'https://s3-ap-south-1.amazonaws.com/hsmediarawimages/',
            'video' => 'https://s3-ap-south-1.amazonaws.com/bfmediavideos/',
            'raw_video' => 'https://s3-ap-south-1.amazonaws.com/bfmediarawvideos/',
            'video_audio' => 'https://s3-ap-south-1.amazonaws.com/',
        ],
    ],
    // END s3

    /*
    |-----------------------------------------------------------------------
    | Kraken https://kraken.io/
    |-----------------------------------------------------------------------
    */

    'kraken' => [
        'api_key' => '71ccc9fe0bff44ec0c225a25d721e97d',
        'api_secret' => '0952ea857f7cd4dcfbbf9e1b9deb7ffe0ed633b7',
    ],

    /*
    |-----------------------------------------------------------------------
    | AWS Cloudfront
    |-----------------------------------------------------------------------
    */

    'cloudfront' => [
        'key' => 'AKIAUTX3RVVRGUWET7DS',
        'secret' => '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',
        'api_distribution_id' => 'EN1XD8QTR7N0O',
        'region' => 'ap-southeast-1',
        'urls' => [
            'base' => 'https://d3oxidb1kcxtza.cloudfront.net',
            's3_endpoint' => [
                //IMAGES
                'bfmediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediaimages\/',
                //VIDEOS
                'bfmediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediavideos\/',
                //AUDIOS
                'hsmediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hsmediaaudios\/',
            ],
            'cf_endpoint' => [
                //IMAGES
                'd3oxidb1kcxtza.cloudfront.net\/',
                'd3oxidb1kcxtza.cloudfront.net\/',
                //VIDEOS
                'd1g9r7lgdp6q07.cloudfront.net\/',
                'd1g9r7lgdp6q07.cloudfront.net\/',
                //AUDIOS
                'd2lwgd2djw7g02.cloudfront.net\/',
                'd2lwgd2djw7g02.cloudfront.net\/',
            ],
            'base_audio' => 'https://d2lwgd2djw7g02.cloudfront.net',
            'base_image' => 'https://d3oxidb1kcxtza.cloudfront.net',
            'base_video' => 'https://d1g9r7lgdp6q07.cloudfront.net',
        ],

    ],
    // END CLOUDFRONT

    /*
    |-----------------------------------------------------------------------
    | AWS Elastic Transcoder
    |-----------------------------------------------------------------------
    */

    'elastictranscoder' => [
        'key' => 'AKIATQOQ3EJWESV4KKXR',
        'secret' => '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',
        'region' => env('AWS_REGION', 'ap-south-1'),
        'audio' => [
            'pipeline_id' => '1611222419997-jfukvq',
            'presets' => [
                'hls64k' => '1351620000001-200071',
                'hls128k' => '1552560396239-g7pxu2',  // custom
                'hls160k' => '1351620000001-200060',
                'hls256k' => '1552560454316-1cr0u7',  // custom
            ],
        ],
        'video' => [
            'pipeline_id' => '1611222419997-jfukvq',
            'presets' => [
                'hls0400k' => '1351620000001-200050', //exist
                //      'hlsAudio' => '1351620000001-200071', //exist
                'hls0600k' => '1351620000001-200040', //exist
                'hls1000k' => '1351620000001-200030', //exist
                'hls1500k' => '1351620000001-200020', //exist
                'hls2000k' => '1351620000001-200010', //exist
                'hls3000k' => '1611322023567-tvosc4',
                'hls1080p' => '1611322892875-e0ge0a',

            ],
            's3_buckets' => [
                'input' => 'bfmediarawvideos',
                'output' => 'bfmediavideos',
            ],
        ],
    ],
    // END TRANSCODER

    // MIGRATE
    'migrate' => [
        's3' => [
            'old' => [
                //IMAGES
                'hswmediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hswmediaimages\/',
                //VIDEOS
                'hswmediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hswmediavideos\/',
                //AUDIOS
                'hswmediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hswmediaaudios\/',
                //RAW IMAGES
                'hswmediarawimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hswmediarawimages\/',
                // RAW VIDEOS
                'hswmediarawvideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hswmediarawvideos\/',
            ],
            'new' => [
                //IMAGES
                'bfmediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediaimages\/',
                //VIDEOS
                'bfmediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediavideos\/',
                //AUDIOS
                'hsmediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hsmediaaudios\/',
                //RAW IMAGES
                'hsmediarawimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/hsmediarawimages\/',
                // RAW VIDEOS
                'bfmediarawvideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediarawvideos\/',
            ],
        ],
    ],
];
