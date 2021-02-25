<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 4/6/18
 * Time: 12:48 PM
 */

namespace App\Services\Notifications;

use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Topic;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
use sngrl\PhpFirebaseCloudMessaging\Notification;

use Config;

class CustomerNotification
{

    private $serverKey = '';

    public function __construct(){}

    public function sendNotificationToCustomer($params)
    {
//        $fcm_device_token = 'dmnGe1uMppw:APA91bETWMOPAIWrkakwim52omAiddNxO0YgDSyS_YA_Z64T4cQqlFKejOkf68PyTtW8XWo2nlyOP5Gh1ivDcYJ4POJjMMsGqUZ4bbW4CRHxInY-2nZEBrSkuz4Py4Xa09qBe3o6Lp86boxlBnDOtjMnuX6LNhGahw';
//        $artist_id = '598aa3d2af21a2355d686de2';

        $fcm_device_token = (!empty($params['fcm_device_token'])) ? trim($params['fcm_device_token']) : "";
        $artist_id = (!empty($params['artist_id'])) ? trim($params['artist_id']) : "";
        $title = (!empty($params['title'])) ? trim($params['title']) : "";
        $priority = (!empty($params['priority'])) ? trim($params['priority']) : "high";
        $body = (!empty($params['body'])) ? trim($params['body']) : "";
//        $topic_id = (!empty($params['topic_id'])) ? trim($params['topic_id']) : "";

        $server_key = $this->getServerKeyByArtist($artist_id);

        if ($server_key != '') {

            $client = new Client();
            $client->setApiKey($server_key);
            $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

            $message = new Message();
            $message->setPriority($priority);
            $message->addRecipient(new Device($fcm_device_token));

            $message->setNotification(new Notification($title, $body));

            $response = $client->send($message);

            $status_code = $response->getStatusCode();

            $logData = [
                'artist_id' => $artist_id,
//                'topic_id' => $topic_id,
                'payload' => $params,
                'notification_meta_info' => '',
                'status_code' => $status_code,
                'type' => 'send_to_customer'
            ];

            $data = array_except($logData, []);
            $log = new \App\Models\Notificationlog($data);
            $log->save();

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



 public function sendNotiToFollowers($params)
    {
        $fcm_device_token =$params['fcm_device_token'];//(!empty($params['fcm_device_token'])) ? trim($params['fcm_device_token']) : "";
        $artist_id =(!empty($params['artist_id'])) ? trim($params['artist_id']) : "";
        $title ="TEST";// (!empty($params['title'])) ? trim($params['title']) : "";
        $priority = (!empty($params['priority'])) ? trim($params['priority']) : "high";
        $body = (!empty($params['body'])) ? trim($params['body']) : "BollyFame Live Started Now,Join Us";

        $server_key = "AAAAFfN7pyw:APA91bFzLL3uHSAESutOS2BsfQkesUo4KjqAUPXj1CpL0u8ZfUS495CbVEBXoCNGyDL50N00MN8XvX5Ed7Rkm6x3PoiZ9nrxcTHgwAtmVJuoZlYpOGnDWzM0cd754Hc0NSs76ZXVVUPu";//$this->getServerKeyByArtist($artist_id);
        if ($server_key != '') {
            $client = new Client();
            $client->setApiKey($server_key);
            $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

            $message = new Message();
	    $message->setPriority($priority);
	    foreach($fcm_device_token as $token)
	    {
		$message->addRecipient(new Device($token));
	    }
            $message->setNotification(new Notification($title, $body));

            $response = $client->send($message);
            $status_code = $response->getStatusCode();
            $logData = [
                'artist_id' => $artist_id,
//                'topic_id' => $topic_id,
                'payload' => $params,
                'notification_meta_info' => '',
                'status_code' => $status_code,
                'type' => 'send_to_artist_followers'
            ];

            $data = array_except($logData, []);
            $log = new \App\Models\Notificationlog($data);
            $log->save();

            return $status_code;
        }else
        {

        }
    }











}
