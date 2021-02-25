<?php

return[


	  /*
    |-----------------------------------------------------------------------
    | AWS Cloudfront
    |-----------------------------------------------------------------------
    */

    'cloudfront' => [
        'api_distribution_id' => 'E2TV8TZLF6ZD8S',
        'urls'  => [
            'base'  => 'https://d29vvanq3yjz2r.cloudfront.net',
            's3_endpoint' => [
                'sportsmedia.s3.ap-south-1.amazonaws.com'
                           ],
            'cf_endpoint' => [
 	        'd29vvanq3yjz2r.cloudfront.net'     
            ],
             'base_image' => 'https://d29vvanq3yjz2r.cloudfront.net',
       ],

    ],
    // END CLOUDFRONT


];

?>
