<?php

namespace App\Services\SocialShare;

use Facebook;

//use Facebook\Exceptions\FacebookSDKException;
//use Facebook\Exceptions\FacebookResponseException;
//use Facebook\Exceptions\FacebookAuthenticationException;

use App\Services\SocialShare\Share;
use Config;


class Fb extends Share
{
    protected $share;

    public function __construct(Share $share)
    {
        $this->share = $share;
    }

    public function postFeed($content_id, $artist_id)
    {
        $error_messages = $results = [];

        $response = $this->share->getArtistData($content_id, $artist_id);

        $base_video_audio_url = Config::get('app.base_video_audio_url');
        $payload = [];

        $artistInfo = (!empty($response['artist'])) ? $response['artist'] : [];

        $contentInfo = (!empty($response['content'])) ? $response['content'] : [];

        if (empty($artistInfo->fb_access_token)) {

            $error_messages = [
                'type' => '',
                'message' => 'Facebook Access Token is empty for this artist',
                'file' => '',
                'line' => ''
            ];

        }

        if (empty($artistInfo->fb_page_name)) {

            $error_messages = [
                'type' => '',
                'message' => 'Facebook Page name field is empty for this artist',
                'file' => '',
                'line' => ''
            ];
        }

        if (empty($error_messages)) {
            if (!empty($artistInfo->fb_access_token) && !empty($artistInfo->fb_page_name)) {

                $access_token = !empty($artistInfo->fb_access_token) ? $artistInfo->fb_access_token : '';
                $page_name = !empty($artistInfo->fb_page_name) ? $artistInfo->fb_page_name : '';

                $uri = 'https://graph.facebook.com/me/accounts?access_token=' . $access_token;
                $ch = curl_init($uri);
                $headers = array();
                curl_setopt_array($ch, array(
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_VERBOSE => 1
                ));

                $response_page_id = curl_exec($ch);
                curl_close($ch);
                $response = json_decode($response_page_id, true);

                if (!empty($response['error'])) {
                    $error_messages = [
                        'type' => '',
                        'message' => $response['error']['message'],
                        'file' => '',
                        'line' => ''
                    ];
                }

                if (empty($error_messages)) {

                    if (!empty($response['data']))
                        $res = $response['data'];

                    $page_id = '';
                    foreach ($res as $curlKey => $curlVal) {
                        if ($curlVal['name'] == $page_name) {
                            $page_id = $curlVal['id'];
                        }
                    }
                    
//                    $page_id='682188011985719'; // Adveti Page
                    
                    $facebook = new \Facebook\Facebook([
                        'app_id' => $artistInfo->fb_api_key,
                        'app_secret' => $artistInfo->fb_api_secret,
                        'default_graph_version' => 'v2.5',
                    ]);

                    try {
//---------------------------------------------------------Access Token Extend-----------------------------------------------------

                        $longLivedToken = $facebook->getOAuth2Client()->getLongLivedAccessToken($access_token);
                        $facebook->setDefaultAccessToken($longLivedToken);

//---------------------------------------------------------Access Token Extend-----------------------------------------------------

                        $response = $facebook->sendRequest('GET', $page_id, ['fields' => 'access_token'])->getDecodedBody();
                        $fb_page_access_token = $response['access_token'];

                    } catch (Facebook\Exceptions\FacebookResponseException $e) {
                        $error_messages = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];

                    } catch (Facebook\Exceptions\FacebookSDKException $e) {
                        $error_messages = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];
                    } catch (Facebook\Exceptions\FacebookAuthenticationException $e) {
                        $error_messages = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];
                    } catch (Exceptions $e) {
                        $error_messages = [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];
                    }
                }

                if (!empty($fb_page_access_token) && !empty($contentInfo)) {

                    $payload['published'] = true;
                    // create payload acc to type
                    if ($contentInfo->type == 'photo') {
                        $image_url = isset($contentInfo->photo['cover']) ? $contentInfo->photo['cover'] : '';
                        $payload['url'] = $image_url;
                        // $payload['link']	= $image_url;
                        $payload['caption'] = $contentInfo->name;
                        $destination_url = '/' . $page_id . '/photos';

                        if ($image_url != '') {
                            $results = $this->postOnPage($facebook, $destination_url, $payload, $fb_page_access_token);
                        }

                    } elseif ($contentInfo->type == 'video') {

                        if (isset($contentInfo->video) && $contentInfo->video['player_type'] == 'internal') {

                            $video_url = isset($contentInfo->vod_job_data) && $contentInfo->vod_job_data['object_name'] != '' ? $base_video_audio_url . "/" . $contentInfo->vod_job_data['bucket'] . "/" . $contentInfo->vod_job_data['object_name'] : '';

                            $payload['file_url'] = $video_url;
                            $payload['description'] = $contentInfo->name;

                            $destination_url = '/' . $page_id . '/videos';

                            if ($video_url != '') {
                                $results = $this->postOnPage($facebook, $destination_url, $payload, $fb_page_access_token);
                            }

                        } elseif (isset($contentInfo->video) && $contentInfo->video['player_type'] == 'youtube') {

                            $video_url = isset($contentInfo->video['embed_code']) && $contentInfo->video['embed_code'] != '' ? 'https://www.youtube.com/watch?v=' . $contentInfo->video['embed_code'] : '';

                            $payload['source'] = $video_url;
                            $payload['link'] = $video_url;
                            $payload['picture'] = $video_url . '/0.jpg';
                            $payload['description'] = $contentInfo->name;

                            $destination_url = '/' . $page_id . '/feed';

                            if ($video_url != '') {
                                $results = $this->postOnPage($facebook, $destination_url, $payload, $fb_page_access_token);
                            }
                        }
                    } elseif ($contentInfo->type == 'audio') {

                        if (isset($contentInfo->audio) && $contentInfo->duration != '') {

                            $audio_url = isset($contentInfo->aod_job_data) && $contentInfo->aod_job_data['object_name'] != '' ? $base_video_audio_url . "/" . $contentInfo->aod_job_data['bucket'] . "/" . $contentInfo->aod_job_data['object_name'] : '';

                            $payload['file_url'] = $audio_url;
                            $payload['description'] = $contentInfo->name;

                            $destination_url = '/' . $page_id . '/audios';

                            if ($audio_url != '') {
                                $results = $this->postOnPage($facebook, $destination_url, $payload, $fb_page_access_token);
                            }
                        }
                    } else {

                        $payload['message'] = $contentInfo->name;

                        $destination_url = '/' . $page_id . '/feed';

                        $results = $this->postOnPage($facebook, $destination_url, $payload, $fb_page_access_token);
                    }
                }
            }
        }

        if (!empty($results['error_messages'])) {
            $error_messages = $results['error_messages'];
            $results = [];
        }

        if (!empty($results['results'])) {
            $results = $results['results'];
            $error_messages = [];
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function postOnPage($facebook, $destination_url, $payload, $fb_page_access_token)
    {
        $error_messages = $response = [];

        try {
            $response = $facebook->post(
                $destination_url,
                $payload,
                $fb_page_access_token
            );

        } catch (Facebook\Exceptions\FacebookAuthenticationException $e) {
            $error_messages = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            $error_messages = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            $error_messages = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

        } catch (Exception $e) {
            $error_messages = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }

        return ['error_messages' => $error_messages, 'results' => $response];
    }
}




