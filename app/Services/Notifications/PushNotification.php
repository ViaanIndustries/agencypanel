<?php

namespace App\Services\Notifications;

use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Topic;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
use sngrl\PhpFirebaseCloudMessaging\Notification;

use Config;


class PushNotification
{


    /**
     * @var $serverKey
     */
    private $serverKey = '';


    public function __construct()
    {

        //$serverKey = 'AAAAxVBoWj0:APA91bHCBc0enJm0Rq0gkUg5uk4SMS1BoDPonk6GnvFh9JB9THIffnHKtMEAsU6KTEIldOJZXfnmKLbD664G3WduUIeKgHI3ePnzWSqMRw89RotvRi--0pjbpIQtT4npPn-JgTkpaVnx';
        // $serverKey = Config::get('gcp.firebase_server_key');
        // $this->setServerKey($serverKey);
    }

    /**
     * Getter of client
     *
     * @return $serverKey
     */
    protected function getServerKey()
    {
        return $this->serverKey;
    }

    /**
     * Setter of key
     *
     * @param $serverKey
     *
     * @return $this
     */
    private function setServerKey($serverKey)
    {
        $this->serverKey = $serverKey;
        return $this;
    }


    /*
    *  Send message to Device
    */
    public function sendNotificationToDevice($params)
    {

        //print_pretty($params);exit;
        $device_token = (isset($params['device_token']) && $params['device_token'] != '') ? trim($params['device_token']) : "";

        $artist_id = (isset($params['artist_id']) && $params['artist_id'] != '') ? trim($params['artist_id']) : "";

        $title = (isset($params['title']) && $params['title'] != '') ? trim($params['title']) : "";
        $body = (isset($params['body']) && $params['body'] != '') ? trim($params['body']) : "";
        $icon_url = (isset($params['icon_url']) && $params['icon_url'] != '') ? trim($params['icon_url']) : "";
        $deep_link = (isset($params['deep_link']) && $params['deep_link'] != '') ? trim($params['deep_link']) : "";
        $priority = (isset($params['priority']) && $params['priority'] != '') ? trim($params['priority']) : "high";


        $server_key = $this->getServerKeyByArtist($artist_id);

//        \Log::info('ARTIST ID ===> ' . json_encode($artist_id));
//        \Log::info('ARTIST TOPIC ID ===> ' . json_encode($topic_id));
//        \Log::info('ARTIST FIREBASE SERVER KEY ===> ' . json_encode($server_key));
//        \Log::info('ARTIST NOTIFICAION META INFO ===> ' . json_encode($notificationMetaInfo));


        if ($server_key != '') {

            $client = new Client();
            $client->setApiKey($server_key);
            $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

            $message = new Message();
            $message->setPriority($priority);
            $message->addRecipient(new Device($device_token));

            $message
                ->setNotification(new Notification($title, $body))
                //            ->setData(['deep_link' => $deep_link])
                //->setData(['icon'=> 'https://placeholdit.imgix.net/~text?txtsize=14&txt=ICON&w=100&h=100'])
            ;

            $response = $client->send($message);
            $status_code = $response->getStatusCode();
            //        $response_body  =  $response->getBody()->getContents();
            return $status_code;
        }
    }


    /*
    *  Subscribe user to the topic
    */
    public function subscribeUserToTopic($params)
    {

        $artist_id = (isset($params['artist_id']) && $params['artist_id'] != '') ? trim($params['artist_id']) : "";
        $topic_id = (isset($params['topic_id']) && $params['topic_id'] != '') ? trim($params['topic_id']) : "";
        $device_token = (isset($params['device_token']) && $params['device_token'] != '') ? trim($params['device_token']) : "";
        $server_key = $this->getServerKeyByArtist($artist_id);

        if ($server_key != '') {

            $client = new Client();
            $client->setApiKey($server_key);
            $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());
            //print_pretty($params);exit;

            $device_tokens = [$device_token];
            $response = $client->addTopicSubscription($topic_id, $device_tokens);

            $status_code = $response->getStatusCode();
            $response_body = $response->getBody()->getContents();
            return $status_code;
        }


    }



    /*
    *  Remove user subscription to the topic
    */


    /*
    *  Send message to Topic
    */

    public function sendNotificationToTopic($params)
    {
        $artist_id = (isset($params['artist_id']) && $params['artist_id'] != '') ? trim($params['artist_id']) : "";
        $topic_id = (isset($params['topic_id']) && $params['topic_id'] != '') ? trim($params['topic_id']) : "";
        $deeplink = (isset($params['deeplink']) && $params['deeplink'] != '') ? trim($params['deeplink']) : "";
        $title = (isset($params['title']) && $params['title'] != '') ? trim($params['title']) : "";
        $body = (isset($params['body']) && $params['body'] != '') ? trim($params['body']) : "";
        $description = (isset($params['description']) && $params['description'] != '') ? trim($params['description']) : "";
        $group_id = (isset($params['group_id']) && $params['group_id'] != '') ? trim($params['group_id']) : time();
        $content_id = (isset($params['content_id']) && $params['content_id'] != '') ? trim($params['content_id']) : "";
        $icon_url = (isset($params['icon_url']) && $params['icon_url'] != '') ? trim($params['icon_url']) : "";
        $cover_url = (isset($params['icon_url']) && $params['icon_url'] != '') ? trim($params['icon_url']) : "";
        $priority = (isset($params['priority']) && $params['priority'] != '') ? trim($params['priority']) : "high";
        $content_available = (isset($params['content_available']) && $params['content_available'] != '') ? trim($params['content_available']) : true;
        $notificationMetaInfo = ['sound' => true, 'vendor' => 'razrcorp', 'content_available' => true];

        if ($title != '') {
            $notificationMetaInfo['title'] = trim($params['title']);
        }

        if ($body != '') {
            $notificationMetaInfo['body'] = trim($params['body']);
        }

        if ($deeplink != '') {
            $notificationMetaInfo['deeplink'] = trim($params['deeplink']);
        }

        if ($content_id != '') {
            $notificationMetaInfo['content_id'] = trim($params['content_id']);
        }

        if ($icon_url != '') {
            $notificationMetaInfo['icon_url'] = trim($params['icon_url']);
        }

        if ($cover_url != '') {
            $notificationMetaInfo['cover_url'] = trim($params['cover_url']);
        }

        $notificationMetaInfo['description'] = trim($description);
        $notificationMetaInfo['group_id'] = trim($group_id);


        $server_key = $this->getServerKeyByArtist($artist_id);

//        \Log::info('ARTIST ID ===> ' . json_encode($artist_id));
//        \Log::info('ARTIST TOPIC ID ===> ' . json_encode($topic_id));
//        \Log::info('ARTIST FIREBASE SERVER KEY ===> ' . json_encode($server_key));
//        \Log::info('ARTIST NOTIFICAION META INFO ===> ' . json_encode($notificationMetaInfo));

//        var_dump($artist_id); var_dump($server_key);
//        print_pretty($notificationMetaInfo); exit;

        if ($server_key != '') {

            try {
                $client = new Client();
                $client->setApiKey($server_key);
                $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

                $message = new Message();
                $message->setPriority($priority);
                $message->addRecipient(new Topic($topic_id));

//                if(!empty($notificationMetaInfo)) {
//                    $message->setNotification(new Notification($title, $body))->setSound(true)->setData($notificationMetaInfo);
//                }else{
//                    $message->setNotification(new Notification($title, $body))->setSound(true);
//                }


                $notification = new Notification($title, $body);
                $notification->setTitle($title)->setSound(true)->setBody($body);

                if (!empty($notificationMetaInfo)) {
                    $message->setNotification($notification)->setData($notificationMetaInfo);
                } else {
                    $message->setNotification($notification);
                }

//                $message->setData($notificationMetaInfo);
                // var_dump($message);// exit;



               //$response = $client->send($message);
               //$status_code = $response->getStatusCode();
               //$response_body = $response->getBody()->getContents();



                $status_code    =  200;
                $response_body  =  [];

                $logData = [
                    'artist_id' => $artist_id,
                    'topic_id' => $topic_id,
                    'payload' => $params,
                    'notification_meta_info' => $notificationMetaInfo,
                    'status_code' => $status_code,
                    'type' => 'send_to_topic'
                ];

                $data = array_except($logData, []);
                $log = new \App\Models\Notificationlog($data);
                $log->save();

                \Log::info('NOTIFICAION STATUS ===> ' . json_encode($status_code));

            } catch (Exception $e) {

                $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                $response = ['error' => true, 'status_code' => 400, 'reason' => $message['type'] . ' : ' . $message['message'] . ' in ' . $message['file'] . ' on ' . $message['line']];
                $log = new \App\Models\Notificationlog($response);
                $log->save();

                Log::info('SEND NOTIFICATION ERRO  Error : ' . json_encode($response));
            }

            return $status_code;
        }

    }



    public function getServerKeyByArtist($artist_id)
    {
        $server_key = '';
        if ($artist_id != "") {
            $artistConfig = \App\Models\Artistconfig::where('artist_id', trim($artist_id))->first();
            if ($artistConfig) {
                $server_key = (isset($artistConfig['fcm_server_key']) && $artistConfig['fcm_server_key'] != '') ? trim($artistConfig['fcm_server_key']) : "";
            }
        }
        return $server_key;
    }




}
