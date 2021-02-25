<?php

namespace Application\Site\AdminBundle\Services;
use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
use sngrl\PhpFirebaseCloudMessaging\Notification;

class NotifyService
{
    public function sendNotification($token, $title, $body, $link)
    {
        $server_key = $this->getServerKey();
        $client = new Client();
        $client->setApiKey($server_key);
        $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

        $message = new Message();
        $message->setPriority('high');
        $message->addRecipient(new Device($token));

        $message
            ->setNotification(new Notification($title, $body))
            ->setData(['deeplink' => $link])
            //->setJsonData(['icon'=> 'https://placeholdit.imgix.net/~text?txtsize=14&txt=ICON&w=100&h=100'])
        ;

        $response = $client->send($message);

        return $response->getStatusCode();
    }

    /**
     * @var $serverKey
     */
    private $serverKey;

    /**
     * @param array  $config
     */
    public function __construct($config)
    {
        $serverKey = $config['firebase']['key'];

        $this->setServerKey($serverKey);
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

}