<?php
namespace App\Services;

use Config, File, Log, Carbon;

use \Aws\Exception\AwsException;
use League\Flysystem\Exception;


class AwsCloudfront
{

    function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    public function invalidate($params)
    {


        $error_messages = $results = [];

        return ['error_messages' => $error_messages, 'results' => [ 'error' => false ]];


        try {

            $jobData = [];
            $job_id = '';
            $job_status = '';
            $items = (isset($params['urls'])) ? $params['urls'] : [];
            if (count($items) > 0) {
                $distribution_id = (isset($params['distribution_id'])) ? $params['distribution_id'] : Config::get('cloudfront.api_distribution_id');
                $quantity = count($items);
                $caller = (string)time();

                $cloudFront = new \Aws\CloudFront\CloudFrontClient([
                    'version' => '2018-06-18',
                    'region' => 'ap-southeast-1',
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY')
                    ]
                ]);
                $create_invalidate_job_result = $cloudFront->createInvalidation([
                    'DistributionId' => $distribution_id, // REQUIRED
                    'InvalidationBatch' => [ // REQUIRED
                        'CallerReference' => $caller, // REQUIRED
                        'Paths' => [ // REQUIRED
                            'Items' => $items, // items or paths to invalidate
                            'Quantity' => $quantity // REQUIRED (must be equal to the number of 'Items' in the previus line)
                        ]
                    ]
                ]);

//                dd($create_invalidate_job_result);

                if (isset($create_invalidate_job_result['Invalidation'])) {

                    $jobData = [
                        'Invalidation' => $create_invalidate_job_result['Invalidation'],
                        'Location' => (isset($create_invalidate_job_result['Location'])) ? $create_invalidate_job_result['Location'] : "",
                        '@metadata' => (isset($create_invalidate_job_result['@metadata'])) ? $create_invalidate_job_result['@metadata'] : []
                    ];


                    $job_id = $create_invalidate_job_result['Invalidation']['Id'];
                    $job_status = strtolower($create_invalidate_job_result['Invalidation']['Status']);

                }

                $results = [
                    'job_data' => $jobData, 'job_id' => $job_id, 'job_status' => $job_status, 'error' => false
                ];

            } else {
                $error_messages = ['error' => true, 'message' => 'Invalidation urls cannot be empty'];
                Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);
            }

        } catch (Exception $e) {

            // output error message if fails
            // echo $e->getMessage(); echo "\n";
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);

        }

        return ['error_messages' => $error_messages, 'results' => $results];


    }


    public function invalidateContents()
    {
        $invalidate_result = [];
        $params = ['urls' => ['/api/1.0/contents/lists*', '/api/1.0/content/detail*']];
        try {

            $invalidate_result = $this->invalidate($params);

        } catch (\Exception $e) {

            $error_messages = [
                'error' => true,
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::info('AwsCloudfront - Invalidation  : Fail ', $error_messages);

        }

        return $invalidate_result;
    }

    public function invalidateContentDetail()
    {

        $params = ['urls' => ['/api/1.0/content/detail*']];
        $invalidate_result = $this->invalidate($params);
        return $invalidate_result;
    }


    public function invalidateBuckets()
    {

        $params = ['urls' => ['/api/1.0/buckets/lists*']];
        $invalidate_result = $this->invalidate($params);

        return $invalidate_result;
    }



    public function invalidatePackages()
    {

        $params = ['urls' => ['/api/1.0/packages/lists*']];
        $invalidate_result = $this->invalidate($params);
        return $invalidate_result;
    }


    public function invalidateGifts()
    {

        $params = ['urls' => ['/api/1.0/gifts/lists*']];
        $invalidate_result = $this->invalidate($params);
        return $invalidate_result;
    }



    public function invalidateComments()
    {

        $params = ['urls' => ['/api/1.0/comments/lists*']];
        $invalidate_result = $this->invalidate($params);
        return $invalidate_result;
    }


    public function invalidateHomePageSections()
    {
        $params = ['urls' => ['/api/1.0/homepage*']];
        $invalidate_result = $this->invalidate($params);
        return $invalidate_result;
    }
}
