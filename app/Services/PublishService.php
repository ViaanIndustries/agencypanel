<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use App\Services\Gcp;
use Session;
use Storage;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\PubSub\PubSubClient;


class PublishService
{
    public function __construct()
    {
        $file         = config_path().'/Firstpost-pub-sub.json';

        $this->pubsub = new PubSubClient(['keyFile' => json_decode(file_get_contents($file), true)]);

        // Authenticating with a keyfile path.
        $this->pubsub = new PubSubClient(['keyFilePath' => $file]);

        $this->storage = new StorageClient(['keyFile' => json_decode(file_get_contents($file), true)]);

        // Authenticating with a keyfile path.
        $this->storage = new StorageClient(['keyFilePath' => $file]);
    }

    public function publishmessage($request)
    {
        $topic = $this->pubsub->topic('arms');
        $message="hello !!";

        for ($i=0; $i < 10 ; $i++) {

            $message = 'Number :'.$i ;

            $topic->publish([
                'data' => $message,
                'attributes' => [
                    'location' => 'Detroit'
                ]
            ]);

            print('Message published' . PHP_EOL);
        }
    }

    public function receivemessage()
    {
        $subscription = $this->pubsub->subscription('arms_subscription');

        // Pull all available messages.
        $messages = $subscription->pull();
        //print_r($messages);exit;
        foreach ($messages as $message) {
            echo $message->data();
            //echo $message->attributes('location');
            $subscription->acknowledge($message);
        }
        echo "messages are processed";
        //printf('Subscription deleted: %s' . PHP_EOL, $subscription->name());
    }


    public function deleteSubscription($requestData)
    {
        $subscriptionName  = (isset($requestData['subscription_name']) && $requestData['subscription_name'] != '') ? $requestData['subscription_name'] : '';

        $subscription = $this->pubsub->subscription($subscriptionName);

        // Deletes subscription that is no longer needed.
        $subscription->delete();

        printf('Subscription deleted: %s' . PHP_EOL, $subscription->name());
    }


    public function deleteTopic($requestData)
    {
        $topicName  = (isset($requestData['topic_name']) && $requestData['topic_name'] != '') ? $requestData['topic_name'] : '';
        $topic = $this->pubsub->topic($topicName);
        $topic->delete();

        printf('Topic deleted: %s' . PHP_EOL, $topic->name());
    }


    public function receiveMessageFromStorage()
    {

        $subscription = $this->pubsub->subscription('arms_subscription');

        // Pull all available messages.
        $messages = $subscription->pull();
        //print_r($messages);exit;
        foreach ($messages as $message) {
            //$ackID = $message->ackID;
            //print_r($message->info());
            $data = $message->data();
            $messagePayload = json_decode($data);
            $eventType= $message->attribute('eventType');
            //echo $messagePayload->bucket;exit;

            if($eventType =='OBJECT_FINALIZE')
            {
                //if new object is added to the bucket

                $this->storage->registerStreamWrapper();
                $file_name = 'gs://'.$messagePayload->bucket.'/'.$messagePayload->name;
                $contents = file_get_contents($file_name);
                // //echo $contents;
                $url = $file_name;
                //download image from bucket to local storage
                $name = substr($url, strrpos($url, '/') + 1);
                Storage::put($name, $contents);
                echo " download finished";
            }elseif($eventType =='OBJECT_DELETE')
            {
                //if new object is deleted from the bucket
                echo 'gs://'.$messagePayload->bucket.'/'.$messagePayload->name.'file is deleted';
            }



            //ackonwledge message
            $subscription->acknowledge($message);

        }
    }

}