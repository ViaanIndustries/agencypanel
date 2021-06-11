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
    | Agoraa
    |-----------------------------------------------------------------------
    */
    'agora' => [
        'agora_id'                            =>   env('AGORA_APP_ID'),
        'pubnub_publish_key'                  => "pub-c-1e93a7a5-78cd-4fab-9857-20200a613ff6",
        'pubnub_subcribe_key'                 =>"sub-c-4bf407fe-8629-11ea-885f-2621b2dc68c7" ,

    ],
   //Agora End


    /*
    |-----------------------------------------------------------------------
    | Mail
    |-----------------------------------------------------------------------
    */

    // SES -- Simple Email Service
    'mail' => [
        'driver'                => env('MAIL_DRIVER', 'smtp'),
        'host'                  => env('MAIL_HOST', ''),
        'port'                  => env('MAIL_PORT', 587),
        'encryption'            => env('MAIL_ENCRYPTION', 'tls'),
        'username'              => env('MAIL_USERNAME', ''),
        'password'              => env('MAIL_PASSWORD', ''),
        'sendmail'              => '/usr/sbin/sendmail -bs',
        'pretend'               => false,
        'from'                  => array('address' => 'support@bollyfame.com', 'name' => 'Bolly Fame'),
        'from_for_transaction'  => array('address' => 'noreply@bollyfame.com', 'name' => 'Bolly Fame'),
        'from_for_support'      => array('address' => 'support@bollyfame.com', 'name' => 'Bolly Fame'),
        'bcc_email_ids'         => [],
        'bcc_for_transaction'   => ['accounts@bollyfame.com'],
        'bcc_forgot_password'   => [],
    ],

    // END Mail

//    // pepipost -- https://pepipost.com/
//    'mail_pepipost' => [
//        'driver'                => env('MAIL_PEPIPOST_DRIVER', 'smtp'),
//        'host'                  => env('MAIL_PEPIPOST_HOST', 'smtp.pepipost.com'),
//        'port'                  => env('MAIL_PEPIPOST_PORT', 587),
//        'encryption'            => env('MAIL_PEPIPOST_ENCRYPTION', 'tls'),
//        'username'              => env('MAIL_PEPIPOST_USERNAME', 'developerl06tdh'),
//        'password'              => env('MAIL_PEPIPOST_PASSWORD', 'Nefelibata23@#'),
//        'sendmail'              => '/usr/sbin/sendmail -bs',
//        'pretend'               => false,
//        'from'                  => array('address' => 'support@bollyfame.com', 'name' => 'Bolly Fame'),
//        'from_for_transaction'  => array('address' => 'noreply@bollyfame.com', 'name' => 'Bolly Fame'),
//        'from_for_support'      => array('address' => 'support@bollyfame.com', 'name' => 'Bolly Fame'),
//        'bcc_email_ids'         => [], // 'info@bollyfame.com'
//        'bcc_for_transaction'   => ['accounts@bollyfame.com'],
//        'bcc_forgot_password'   => [],
//    ],
//    // END Mail


    /*
    |-----------------------------------------------------------------------
    | Cache
    |-----------------------------------------------------------------------
    */

    'cache' => [
        'aws_elastic_cache_cluster_endpoint'    => env('AWS_ELASTIC_CACHE_CLUSTER'),
    ],
    // END cache

    /*
    |-----------------------------------------------------------------------
    | S3
    |-----------------------------------------------------------------------
    */

    's3'    => [
        /*
        |-------------------------------------------------------------------
        | S3 Access Keys
        |-------------------------------------------------------------------
        */

        'key'               => env('AWS_ACCESS_KEY_ID'),
        'secret'            => env('AWS_SECRET_ACCESS_KEY'),
        'region'            => env('AWS_S3_REGION', 'ap-south-1'),
        'acl'               => env('AWS_S3_ACL', 'private'),
        'bucket'            => env('AWS_S3_DEFUALT_BUCKET', 'bollymediaimages'),

        /*
        |-------------------------------------------------------------------
        | S3 Access Urls Used By Browsers
        |-------------------------------------------------------------------
        */

        'cloudfront_image_base_url' => 'https://assets-images.bollyfame.com/',
        'cloudfront_video_base_url' => 'https://assets-videos.bollyfame.com/',
        'cloudfront_audio_base_url' => 'https://dfqwvgp874nmb.cloudfront.net/',

        'rawvideos' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediarawvideos',
        ],

        'videos' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediavideos',
        ],

        'rawaudios' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediarawaudios',
        ],

        'audios' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediaaudios',
        ],

        'rawimages' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediarawimages',
        ],

        'images' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_S3_REGION', 'ap-south-1'),
            'bucket' => 'bollymediaimages',
        ],

        'base_urls'  => [
            'audio'         => 'https://s3-ap-south-1.amazonaws.com/bollymediaaudios/',
            'raw_audio'     => 'https://s3-ap-south-1.amazonaws.com/bollymediarawimages/',
            'photo'         => 'https://s3-ap-south-1.amazonaws.com/bollymediaimages/',
            'raw_photo'     => 'https://s3-ap-south-1.amazonaws.com/bollymediarawimages/',
            'video'         => 'https://s3-ap-south-1.amazonaws.com/bollymediavideos/',
            'raw_video'     => 'https://s3-ap-south-1.amazonaws.com/bollymediarawvideos/',
            'video_audio'   => 'https://s3-ap-south-1.amazonaws.com/',
        ],
    ],
    // END s3

    /*
    |-----------------------------------------------------------------------
    | Kraken https://kraken.io/
    |-----------------------------------------------------------------------
    */

    'kraken' => [
        'api_key'       => '71ccc9fe0bff44ec0c225a25d721e97d',
        'api_secret'    => '0952ea857f7cd4dcfbbf9e1b9deb7ffe0ed633b7',
    ],

    /*
    |-----------------------------------------------------------------------
    | AWS Cloudfront
    |-----------------------------------------------------------------------
    */

    'cloudfront' => [
        'key'       => 'AKIAUTX3RVVRGUWET7DS',
        'secret'    => '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',
        'api_distribution_id' => 'EN1XD8QTR7N0O',
        'region'    => 'ap-southeast-1',
        'urls'  => [
            'base'  => 'https://assets-images.bollyfame.com',
            's3_endpoint' => [
                //IMAGES
                'bollymediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediaimages\/',
                //VIDEOS
                'bollymediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediavideos\/',
                //AUDIOS
                'bollymediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediaaudios\/',

                //IMAGESOLD
                'bfmediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediaimages\/',
                //VIDEOSOLD
                'bfmediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediavideos\/',

            ],
            'cf_endpoint' => [
                //IMAGES
                'assets-images.bollyfame.com\/',
                'assets-images.bollyfame.com\/',
                //VIDEOS
                'assets-videos.bollyfame.com\/',
                'assets-videos.bollyfame.com\/',
                //AUDIOS
                'd2lwgd2djw7g02.cloudfront.net\/',
                'd2lwgd2djw7g02.cloudfront.net\/',

                //IMAGES OLD
                'assets-images.bollyfame.com\/',
                'assets-images.bollyfame.com\/',

                //VIDEOS OLD
                'assets-videos.bollyfame.com\/',
                'assets-videos.bollyfame.com\/',
            ],
            'base_audio' => 'https://d2lwgd2djw7g02.cloudfront.net',
            'base_image' => 'https://assets-images.bollyfame.com',
            'base_video' => 'https://assets-videos.bollyfame.com',
        ],

    ],
    // END CLOUDFRONT

    /*
    |-----------------------------------------------------------------------
    | AWS Elastic Transcoder
    |-----------------------------------------------------------------------
    */

    'elastictranscoder' => [
        'key'       => 'AKIAT5N7CSC5NCBO57BH',
        'secret'    => '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',
        'region'    => env('AWS_REGION', 'ap-south-1'),
        'audio'     => [
            'pipeline_id'   => '1576498268406-udf2gn',
            'presets'       => [
                'hls64k'    => '1351620000001-200071',
                'hls128k'   => '1552560396239-g7pxu2',  // custom
                'hls160k'   => '1351620000001-200060',
                'hls256k'   => '1552560454316-1cr0u7',  // custom
            ],
        ],
        'video'     => [
            'pipeline_id'   => '1611222419997-jfukvq',
            'presets'       => [
                'hls0400k' => '1351620000001-200050', //exist
                'hlsAudio' => '1351620000001-200071', //exist
                'hls0600k' => '1351620000001-200040', //exist
                'hls1000k' => '1351620000001-200030', //exist
                'hls1500k' => '1351620000001-200020', //exist
                'hls2000k' => '1351620000001-200010', //exist
               # 'hls3000k' => '1576677788102-9n7tlv',
               # 'hls1080p' => '1576677896448-t0f51g',

            ],
            's3_buckets'   => [
                'input'     => 'bollymediarawvideos',
                'output'    => 'bollymediavideos',
            ],
        ],
    ],
    // END TRANSCODER


    // MIGRATE
    'migrate' => [
        's3' => [
            'old' => [
                //IMAGES
                'bfmediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediaimages\/',
                //VIDEOS
                'bfmediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediavideos\/',
                //AUDIOS
                'bfmediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfwmediaaudios\/',
                //RAW IMAGES
                'bfwmediarawimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediarawimages\/',
                // RAW VIDEOS
                'bfmediarawvideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bfmediarawvideos\/',
            ],
            'new' => [
                //IMAGES
                'bollymediaimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediaimages\/',
                //VIDEOS
                'bollymediavideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediavideos\/',
                //AUDIOS
                'bollymediaaudios.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediaaudios\/',
                //RAW IMAGES
                'bollymediarawimages.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediarawimages\/',
                // RAW VIDEOS
                'bollymediarawvideos.s3.ap-south-1.amazonaws.com\/',
                's3-ap-south-1.amazonaws.com\/bollymediarawvideos\/',
            ],
        ],
    ],
];
