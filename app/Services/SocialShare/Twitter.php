<?php

namespace App\Services\SocialShare;

//use App\Services\TwitterServicePost;
use App\Services\SocialShare\Share as Share;
use Codebird\Codebird;
use Config;

class Twitter extends Share
{
    protected $share;
    protected $twitterservicepost;

    public function __construct(
        Share $share
        //    TwitterServicePost $twitterservicepost
    )
    {
        $this->share = $share;
//        $this->twitterservicepost = $twitterservicepost;
    }

    public function postFeed($content_id, $artist_id)
    {
//        https://apps.twitter.com/app/14859772/show - To change URL for sharing Post
//        jublonet/codebird-php - PHP library.
        $error_messages = $results = [];
        $base_video_audio_url = Config::get('app.base_video_audio_url');
        $response = $this->share->getArtistData($content_id, $artist_id);

        $artistInfo = isset($response['artist']) && !empty($response['artist']) ? $response['artist'] : [];

        $contentInfo = isset($response['content']) && !empty($response['content']) ? $response['content'] : [];

        $twitter_consumer_key = isset($artistInfo->twitter_consumer_key) ? $artistInfo->twitter_consumer_key : ''; //'lLFmOmLcuSLOe91wlDPNfJGTm';
        $twitter_consumer_key_secret = isset($artistInfo->twitter_consumer_key_secret) ? $artistInfo->twitter_consumer_key_secret : ''; //'jiCB41wrVjxV46UVeof2sDZCEPPoiO8Ow3HBYRrXSStuPNZidL';
        $twitter_oauth_access_token = isset($artistInfo->twitter_oauth_access_token) ? $artistInfo->twitter_oauth_access_token : '';
        $twitter_oauth_access_token_secret = isset($artistInfo->twitter_oauth_access_token_secret) ? $artistInfo->twitter_oauth_access_token_secret : '';

        if ($twitter_consumer_key != '' && $twitter_consumer_key_secret != '' && $twitter_oauth_access_token != '' && $twitter_oauth_access_token_secret != '') {

//            $this->twitterservicepost->setConsumerKey($twitter_consumer_key, $twitter_consumer_key_secret);
//            $twitter = $this->twitterservicepost->getInstance();
//            $twitter->setToken($twitter_oauth_access_token, $twitter_oauth_access_token_secret);

            Codebird::setConsumerKey($twitter_consumer_key, $twitter_consumer_key_secret);
            $twitter = Codebird::getInstance();
            $twitter->setToken($twitter_oauth_access_token, $twitter_oauth_access_token_secret);
            $twitter->setTimeout(60 * 1000); // 60 second request timeout

            if (!empty($contentInfo) && $contentInfo->type == 'photo') {
                $image_url = isset($contentInfo->photo['cover']) ? $contentInfo->photo['cover'] : '';

                if ($image_url != '') {
                    $twitter->statuses_update();

                    $result = $twitter->media_upload([
                        'media' => $image_url
                    ]);

                    $mediaID = $result->media_id_string;

                    $params = [
                        'status' => $contentInfo->name,
                        'media_ids' => $mediaID
                    ];

                    $results = $twitter->statuses_update($params);
                }

            } elseif ($contentInfo->type == 'video') {

                $video_url = isset($contentInfo->vod_job_data) && $contentInfo->vod_job_data['object_name'] != '' ? $base_video_audio_url . "/" . $contentInfo->vod_job_data['bucket'] . "/" . $contentInfo->vod_job_data['object_name'] : '';

                if ($video_url != '') {

                    $headers = get_headers($video_url, 1);
                    $size_bytes = !empty($headers['Content-Length']) ? $headers['Content-Length'] : 0;
                    $file = fopen($video_url, 'rb');

//============================== INIT the upload  ========================================

                    $media = $twitter->media_upload([
                        'command' => 'INIT',
                        'media_type' => 'video/mp4',
                        'media_category' => 'tweet_video',
                        'total_bytes' => $size_bytes,
                    ]);

//============================== INIT the upload  =========================================
//============================== APPEND data to the upload  ===============================

                    $media_id = $media->media_id_string;
                    $segment_id = 0;

                    while (!feof($file)) {
                        $chunk = fread($file, 4 * 1024 * 1024);

                        $media = $twitter->media_upload([
                            'command' => 'APPEND',
                            'media_id' => $media_id,
                            'segment_index' => $segment_id,
                            'media' => $chunk
                        ]);

                        $segment_id++;
                    }

//============================== APPEND data to the upload  ========================================

//============================== FINALIZE the upload  ==============================================

                    fclose($file);

                    $media = $twitter->media_upload([
                        'command' => 'FINALIZE',
                        'media_id' => $media_id
                    ]);

//============================== FINALIZE the upload  ===============================================

//===================================================================================================
// if you have a field `processing_info` in the reply,
// use the STATUS command to check if the video has finished processing.

                    if (isset($media->processing_info)) {
                        $info = $media->processing_info;
                        if ($info->state != 'succeeded') {
                            $attempts = 0;
                            $checkAfterSecs = $info->check_after_secs;
                            $success = false;
                            do {
                                $attempts++;
                                sleep($checkAfterSecs);

                                $media = $twitter->media_upload([
                                    'command' => 'STATUS',
                                    'media_id' => $media_id,
                                ]);

                                $procInfo = $media->processing_info;

                                if ($procInfo->state == 'succeeded' || $procInfo->state == 'failed') {
                                    break;
                                }

                                $checkAfterSecs = $procInfo->check_after_secs;
                            } while ($attempts <= 10);
                        }
                    }

//====================================================================================================

//===================================Now use the media_id in a Tweet==================================

                    $results = $twitter->statuses_update([
                        'status' => 'Twitter now accepts video uploads.',
                        'media_ids' => $media_id
                    ]);

//===================================Now use the media_id in a Tweet==================================
                }
            } else {
                $params = [
                    'status' => $contentInfo->name
                ];
                $results = $twitter->statuses_update($params);
            }

        } else {
            $error_messages[] = 'Some values missng ';
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }
}