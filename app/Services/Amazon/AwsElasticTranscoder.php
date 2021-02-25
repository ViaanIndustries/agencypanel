<?php

namespace App\Services\Amazon;

use Config, File, Log, Carbon, Storage;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use \Aws\Exception\AwsException;

/*
 *  Refernece -
 *  https://docs.aws.amazon.com/pt_br/elastictranscoder/latest/developerguide/preset-settings.html
 *  https://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.ElasticTranscoder.ElasticTranscoderClient.html#_createJob
 *  https://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.ElasticTranscoder.ElasticTranscoderClient.html#_createPreset
 *  https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
 *  https://developer.apple.com/documentation/http_live_streaming/hls_authoring_specification_for_apple_devices#overview
 * (1.14. All interlaced source content MUST be de-interlaced.)
 *
 */


class AwsElasticTranscoder
{


    protected $elasticTranscoderClient;


    public function __construct()
    {

        # Create the client for Elastic Transcoder.
        $this->elasticTranscoderClient = ElasticTranscoderClient::factory([
            'credentials' => [
                'key' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.key'),
                'secret' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.secret'),
            ],
            'region' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.region'),
            'version' => '2012-09-25',
        ]);
    }


    public function createHlsVodJob($params = array())
    {
        Log::info(__METHOD__ . ' START BOLLYFAME', $params);

        $error_messages = $results = [];

        try {

            $s3_input_file = (isset($params['s3_input_file'])) ? trim($params['s3_input_file']) : "";
            $unique_id = (isset($params['unique_id'])) ? trim($params['unique_id']) : "";

            if ($s3_input_file != '' && $unique_id != '') {

//                $pipeline_id = '1552488632050-eqk2sk'; //for aptestvideotranscode   pipeline
                $pipeline_id = Config::get('product.' . env('PRODUCT') . '.elastictranscoder.video.pipeline_id'); //for apvideostranscode pipeline

                $object_name = $s3_input_file;
                $object_name_without_extension = pathinfo($object_name, PATHINFO_FILENAME);
//                $input_key                      =   "estranscodeinput/$object_name";
                $input_key = $object_name;

                # Setup the job input using the provided input key.
//                $input = array('Key' => $input_key);
                $input = ['Key' => $input_key, 'FrameRate' => 'auto', 'Resolution' => 'auto', 'AspectRatio' => 'auto', 'Interlaced' => 'false', 'Container' => 'auto'];
//                $input  = ['Key' => $input_key, 'FrameRate' => 'auto', 'Resolution' => 'auto', 'AspectRatio' => 'auto', 'Interlaced' => 'auto', 'Container' => 'auto'];

                // InputCaptions
                $input_captions = [];
                if (isset($params['captions'])) {
                    $caption_sources = [];
                    foreach ($params['captions'] as $key => $caption) {
                        $caption_source = [];
                        $lang = isset($caption['language']) ? $caption['language'] : 'eng';
                        $lang_label = isset($caption['language_label']) ? $caption['language_label'] : 'English';

                        $caption_file = isset($caption['object_path']) ? $caption['object_path'] : '';
                        if ($caption_file) {
                            // name of the input caption file
                            $caption_source['Key'] = $caption_file;

                            // language of the input caption file
                            $caption_source['Language'] = $lang;

                            // label for the caption
                            $caption_source['Label'] = $lang_label;

                            $caption_sources[] = $caption_source;
                        }
                    }

                    if ($caption_sources) {
                        $input_captions = [
                            // MergeOverride|MergeRetain|Override
                            'MergePolicy' => 'Override',
                            'CaptionSources' => $caption_sources,
                        ];
                    }

                    if ($input_captions) {
                        $input['InputCaptions'] = $input_captions;
                    }
                }

                $time = time();
                $output_key_prefix = "et/$object_name_without_extension/$unique_id/";

                #Setup the job outputs using the HLS presets.
                $output_key = $object_name_without_extension;
                $outputs = $this->getOutputs($params);
                # Setup master playlist which can be used to play using adaptive bitrate.
                $playlist = array(
                    'Name' => 'hls_playlist_' . $output_key,
                    'Format' => 'HLSv3',
                    'OutputKeys' => array_map(function ($x) {
                        return $x['Key'];
                    }, $outputs)
                );


                # Create the job.
                $create_job_request = array(
                    'PipelineId' => $pipeline_id,
                    'Input' => $input,
                    'Outputs' => $outputs,
                    'OutputKeyPrefix' => $output_key_prefix,
                    'Playlists' => array($playlist)
                );


                 Log::info(__METHOD__ . ' $create_job_request', $create_job_request);

                $create_job_result = $this->elasticTranscoderClient->createJob($create_job_request)->toArray();
              //  print_pretty($create_job_result);exit;

                Log::info(__METHOD__ . ' $create_job_result', $create_job_result);
                $jobData = $create_job_result['Job'];
                $job_id = $jobData['Id'];
                $job_status = strtolower($jobData['Status']);

                $results = [
                    'job_data' => $jobData, 'job_id' => $job_id, 'job_status' => $job_status, 'error' => false
                ];

            } else {

                $error_messages = ['error' => true, 'message' => 's3_input_path or s3_output_path cannot be blank'];

            }

        } catch (AwsException $e) {

            // output error message if fails
            // echo $e->getMessage(); echo "\n";
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('AwsElasticTranscoder - createHlsVodJob  : Fail ', $error_messages);

        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }


    public function getOutputs($params = array())
    {
        $outputs = [];


        $s3_input_file = (isset($params['s3_input_file'])) ? trim($params['s3_input_file']) : "";
        $object_name = $s3_input_file;
        $object_name_without_extension = pathinfo($object_name, PATHINFO_FILENAME);

        # HLS Presets that will be used to create an adaptive bitrate playlist.
        $hls_presets = Config::get('product.' . env('PRODUCT') . '.elastictranscoder.video.presets');

        # HLS Segment duration that will be targeted.
        $segment_duration = '10';

        #All outputs will have this prefix prepended to their output key.
//        $output_key_prefix = 'elastic-transcoder-samples/output/hls/';

        #Setup the job outputs using the HLS presets.
//        $output_key = hash('sha256', utf8_encode($input_key));
        $output_key = $object_name_without_extension;


        # Specify the outputs based on the hls presets array spefified.
        $outputs = array();
        foreach ($hls_presets as $prefix => $preset_id) {
            if ($prefix != 'hlsAudio') {
                $output = array('Key' => "$prefix/$output_key", 'PresetId' => $preset_id, 'SegmentDuration' => $segment_duration, 'ThumbnailPattern' => "thumbnail/{$prefix}_{$output_key}{count}");
            } else {
                $output = array('Key' => "$prefix/$output_key", 'PresetId' => $preset_id, 'SegmentDuration' => $segment_duration);
            }

            // CaptionFormats
            $output_captions = [];
            if (isset($params['captions'])) {
                $caption_formats = [];
                $caption_format = [];

                // cea-708|dfxp|mov-text|scc|srt|webvtt
                $caption_format['Format'] = 'webvtt';

                //myCaption/file-language
                $caption_format['Pattern'] = 'captions/' . $output_key . "-{language}";

                $caption_formats[] = $caption_format;

                if ($caption_formats) {
                    $output_captions = ['CaptionFormats' => $caption_formats];
                }
            }

            if ($output_captions) {
                $output['Captions'] = $output_captions;
            }

            array_push($outputs, $output);
        }

        return $outputs;

    }


    public function getJobStatus($jobid)
    {
        $error_messages = $results = [];

        try {
            $jobStatusRequest = $this->elasticTranscoderClient->readJob(['Id' => $jobid]);
             $jobData = $jobStatusRequest->get('Job');
            $job_id = $jobData['Id'];
            $job_status = strtolower($jobData['Status']);

            $results = [
                'job_data' => $jobData,
                'job_id' => $job_id,
                'job_status' => $job_status,
                'error' => false
            ];

        } catch (AwsException $e) {
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('AwsElasticTranscoder - createHlsVodJob  : Fail ', $error_messages);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getListOfPresets()
    {
        $error_messages = $results = [];

        try {

            $lists = $this->elasticTranscoderClient->listPresets()->toArray();
            return $lists;

        } catch (AwsException $e) {
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('AwsElasticTranscoder - getListOfPresets  : Fail ', $error_messages);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getPreset($presetid)
    {
        $error_messages = $results = [];

        try {

            return $detail = $this->elasticTranscoderClient->readPreset(['Id' => $presetid])->toArray();

        } catch (AwsException $e) {
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('AwsElasticTranscoder - getPreset  : Fail ', $error_messages);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function sampleTranscode()
    {

        # Create the client for Elastic Transcoder.
        $ElasticTranscoder = ElasticTranscoderClient::factory([
            'credentials' => [
                'key' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.key'),
                'secret' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.secret'),
            ],
            'region' => Config::get('product.' . env('PRODUCT') . '.elastictranscoder.secret'),
            'version' => '2012-09-25',
        ]);

//        $inputs  = [
//            '1542566162_tmpphp3uto1z.mp4',
//            '636778807780772992.mp4',
//            'app_used_camera.mp4',
//            'test_p_0.mp4',
//            'test_p_1.mp4',
//            'test_p_2.mp4',
//            'test_p_3.mp4',
//            'VID-20181121-WA0013.mp4',
//            'VID20181123181318.mp4',
//
//        ];

        $inputs = [
            'bunny.mp4'
        ];

        foreach ($inputs as $val) {

//            $object_name                    =   "test_p_1.mp4";
            $object_name = $val;
            $object_name_without_extension = pathinfo($object_name, PATHINFO_FILENAME);
            $input_key = "$object_name";

            $time = time();
            $output_key_prefix = "estranscodeoutput/$object_name_without_extension/$time/";


            # HLS Presets that will be used to create an adaptive bitrate playlist.
            $hls_presets = Config::get('product.' . env('PRODUCT') . '.elastictranscoder.video.presets');

            # HLS Segment duration that will be targeted.
            $segment_duration = '2';

            #All outputs will have this prefix prepended to their output key.
//        $output_key_prefix = 'elastic-transcoder-samples/output/hls/';

            # Setup the job input using the provided input key.
            $input = array('Key' => $input_key);

            #Setup the job outputs using the HLS presets.
//        $output_key = hash('sha256', utf8_encode($input_key));
            $output_key = $object_name_without_extension;

            # Specify the outputs based on the hls presets array spefified.
            $outputs = array();
            foreach ($hls_presets as $prefix => $preset_id) {

                $output = array('Key' => "$prefix/$output_key", 'PresetId' => $preset_id, 'SegmentDuration' => $segment_duration);

                array_push($outputs, $output);
            }

            # Setup master playlist which can be used to play using adaptive bitrate.
            $playlist = array(
                'Name' => 'hls_playlist_' . $output_key,
                'Format' => 'HLSv3',
                'OutputKeys' => array_map(function ($x) {
                    return $x['Key'];
                }, $outputs)
            );

//                $pipeline_id = '1552488632050-eqk2sk'; //for aptestvideotranscode   pipeline
            $pipeline_id = Config::get('product.' . env('PRODUCT') . '.elastictranscoder.video.pipeline_id'); //for apvideostranscode pipeline

            # Create the job.
            $create_job_request = array(
                'PipelineId' => $pipeline_id,
                'Input' => $input,
                'Outputs' => $outputs,
                'OutputKeyPrefix' => $output_key_prefix,
                'Playlists' => array($playlist)
            );
//        print_pretty($create_job_request);

            $create_job_result = $ElasticTranscoder->createJob($create_job_request)->toArray();
            $job = $create_job_result['Job'];
            $job_id = $job['Id'];
            $job_status = trim(strtolower($job['Status']));
//            print_pretty($job);

            $base_output_url = "https://s3-ap-southeast-1.amazonaws.com/tests-output/";
            $output_playlist_url = $base_output_url . $output_key_prefix . "hls_playlist_" . $output_key . ".m3u8";

            echo "<br><br>";
            echo "#################################################################";
            echo "<br>OUTPUT PLAYLIST URL :  $output_playlist_url";
            echo "<br>JOB ID :  $job_id";
            echo "<br>JOB STATUS :  $job_status";


        }//foreach


    }


}


