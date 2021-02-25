<?php
namespace App\Services\SocialShare;

use App\Services\InstagramUpload;
use Config;


class Instagram extends Share
{
    protected $instagramupload;

    public function __construct(Share $share, InstagramUpload $instagramupload)
    {
        $this->share = $share;
        $this->instagramupload = $instagramupload;
    }

    public function postFeed($content_id, $artist_id)
    {
        $error_messages = $results = [];

        $response = $this->share->getArtistData($content_id, $artist_id);

        $base_video_audio_url = Config::get('app.base_video_audio_url');

        $artistInfo = isset($response['artist']) && !empty($response['artist']) ? $response['artist'] : [];

        $contentInfo = isset($response['content']) && !empty($response['content']) ? $response['content'] : [];

        if (isset($artistInfo->instagram_user_name) && isset($artistInfo->instagram_password)) {

            if (!empty($contentInfo)) {

                if ($contentInfo->type == 'photo') {

                    try {
                        $this->instagramupload->login($artistInfo->instagram_user_name, $artistInfo->instagram_password);

                        $image_url = isset($contentInfo->photo['cover']) ? $contentInfo->photo['cover'] : '';

                        if ($image_url != '') {
                            $img = public_path('demo.jpg');
                            file_put_contents($img, file_get_contents($image_url));

                            $results = $this->instagramupload->UploadPhoto($img, $contentInfo->name);
                        }

                    } catch (\Exception $e) {
                        $error_messages[] = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];

//                        $error_messages[] = 'Something went wrong with instagram api';
                    }
                } elseif ($contentInfo->type == 'video') { // Instagram currently doesn't allow to filter videos

                    try {
                        $this->instagramupload->login($artistInfo->instagram_user_name, $artistInfo->instagram_password);

                        $video_url = isset($contentInfo->vod_job_data) && $contentInfo->vod_job_data['object_name'] != '' ? $base_video_audio_url . "/" . $contentInfo->vod_job_data['bucket'] . "/" . $contentInfo->vod_job_data['object_name'] : '';

//                        $image_url = 'https://storage.googleapis.com/arms-razrmedia/contents/c/5a5a0ae79353ad458223f924.jpg';

                        $caption = !empty($contentInfo->name) ? $contentInfo->name : '';

//                        if ($image_url != '') {
//                            $img = public_path('demo.jpg');
//                            file_put_contents($img, file_get_contents($image_url));
//
////                            $response = $this->instagramupload->UploadPhoto($img, $caption);
////                            $response = $this->instagramupload->UploadVideo($video_url, $caption);
//                        }


                        $results = $this->instagramupload->UploadVideo($video_url, '', $caption);

                    } catch (\Exception $e) {
                        $error_messages[] = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];

//                        $error_messages[] = 'Something went wrong with instagram api';
                    }

                } else {

                }
            }

        } else {
            $error_messages[] = [
                'type' => '',
                'message' => 'Instagram Credentials missing',
                'file' => '',
                'line' => ''
            ];

//            $error_messages[] = 'Instagram Credentials missing';
        }
        return ['error_messages' => $error_messages, 'results' => $results];

    }
}