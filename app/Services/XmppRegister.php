<?php

namespace App\Services;

use Fabiang\Xmpp\Protocol\ProtocolImplementationInterface;
use Fabiang\Xmpp\Util\XML as XmppXML;


/**
 * Register new user
 * @param string $username
 * @param string $password
 * @param string $email
 * @package XMPP\Protocol
 * @category XMPP
 */

Class XmppRegister implements ProtocolImplementationInterface
{



    protected $username;
    protected $password;
    protected $email;

    /**
     * Constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $email
     */
    public function __construct($username, $password, $email)
    {
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;
    }

    /**
     * Build XML message
     * @return type
     */
    public function toString()
    {
//        $query = "<iq type='set' id='%s'><query xmlns='jabber:iq:register'><username>%s</username><password>%s</password><email>%s</email></query></iq>";
//        return XmppXML::quoteMessage($query, XmppXML::generateId(), (string) $this->username, (string) $this->password, (string) $this->email);

        $query = "%s%s%s";
        return XmppXML::quoteMessage($query, XmppXML::generateId(), (string) $this->username, (string) $this->password, (string) $this->email);
    }








}