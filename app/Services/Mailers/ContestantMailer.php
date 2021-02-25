<?php

namespace App\Services\Mailers;

use Config, Mail;

Class ContestantMailer extends Mailer {

    private $product = 'apm';

    public function __construct() {
        parent::__construct();

        $this->product = env('PRODUCT');
    }

	public function common($email_template, $template_data, $message_data, $label, $priority, $delay = 0) {
        return $this->sendToEmail($email_template, $template_data, $message_data, $label, $priority);
    }

    /**
     * Send Registration Mail to contestant
     *
     * @param   array $data
     *
     * @author  Ruchi <ruchi.sharma@bollyfame.com>
     * @since   2019-07-23
     */
    public function sendRegistrationMail($data) {
        $email_template = 'emails.' . $this->product . '.contestant.registration';

        $message_data = array(
            'user_email'    => $data['customer_email'],
            'user_name'     => $data['customer_name'],
            'bcc_emailids'  => [],
            'email_subject' => 'Registration completed successfully',
            'celeb_name'    =>  Config::get('product.' . env('PRODUCT') . '.app_name'),
        );

        $label = 'email_for_registration-c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

    /**
     * Send Registration Approval Mail to contestant
     *
     * @param   array $data
     *
     * @author  Ruchi <ruchi.sharma@bollyfame.com>
     * @since   2019-07-23
     */
    public function sendApprovalMail($data) {
        $email_template = 'emails.' . $this->product . '.contestant.approval';

        $message_data = array(
            'user_email'    => $data['customer_email'],
            'user_name'     => $data['customer_name'],
            'bcc_emailids'  => [],
            'email_subject' => 'Registration successfully approved to ',
            'celeb_name'    => Config::get('product.' . env('PRODUCT') . '.app_name'),
        );

        $label = 'email_for_registration_approval-c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

}
