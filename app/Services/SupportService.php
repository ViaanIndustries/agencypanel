<?php

namespace App\Services;

use Carbon\Carbon;
use Input, Config, Request, Log;

use App\Repositories\Contracts\FeedbackInterface;
use App\Services\Mailers\CustomerMailer;
use App\Services\Jwtauth;
use App\Services\ArtistService;

class SupportService
{
    protected $feedbackRepObj;
    protected $awscloudfrontService;
    protected $mailer;
    protected $jwtauth;
    protected $artistService;


    public function __construct(
        FeedbackInterface $feedbackRepObj,
        CustomerMailer $customermailer,
        Jwtauth $jwtauth,
        ArtistService $artistService
    )
    {
        $this->feedbackRepObj   = $feedbackRepObj;
        $this->customermailer   = $customermailer;
        $this->jwtauth          = $jwtauth;
        $this->artistService    = $artistService;
    }



    public function saveCustomerFeedback($request)
    {
        $item               =   $request->all();
        $error_messages     =   $results = [];

        $customer           = [];
        $customer_email     = '';
        $customer_name      = '';
        $customer_id        = (!empty($item['customer_id'])) ? trim($item['customer_id']) : '';
        $artist_id          = (!empty($item['artist_id'])) ? trim($item['artist_id']) : '';
        $feedback           = (!empty($item['feedback'])) ? trim($item['feedback']) : '';
        $ratings            = (!empty($item['ratings'])) ? intval($item['ratings']) : '';
        $platform           = (!empty($item['platform'])) ? strtolower(trim($item['platform'])) : '';
        $platform_version   = (!empty($item['v'])) ? trim($item['v']) : '';
        $type               = (!empty($item['type'])) ? strtolower(trim($item['type'])) : 'general';
        $entity             = (!empty($item['entity'])) ? trim($item['entity']) : '';
        $entity_id          = (!empty($item['entity_id'])) ? trim($item['entity_id']) : '';

        // If Customer Id is not given
        // Then find it form token
        if(!$customer_id) {
            $customer_id    = $this->jwtauth->customerIdFromToken();
            $customer       = $this->jwtauth->customerFromToken();
            if(!empty($customer)) {
                $customer_email = (isset($customer['email']) && $customer['email']) ?  trim(strtolower($customer['email'])) : '';
                $customer_name  = generate_fullname($customer);
            }
        }

        if(empty($error_messages)){

            $saveData = [
                'customer_id'       => $customer_id,
                'artist_id'         => $artist_id,
                'feedback'          => $feedback,
                'ratings'           => $ratings,
                'platform'          => $platform,
                'platform_version'  => $platform_version,
            ];

            if($type) {
                $saveData['type'] = $type;
            }

            if($entity) {
                $saveData['entity'] = $entity;
            }

            if($entity_id) {
                $saveData['entity_id'] = $entity_id;
            }

            $feedback_saved = $this->feedbackRepObj->store($saveData);

            if($feedback_saved && $customer_email) {

                // New Code for Sending Email
                $celebname      = '';
                $subject_line   = 'Customer Feedback - ' . ucwords($type);
                $user_email     = '';

                $payload = [];
                // Get Email Default Template Data
                if($artist_id) {
                    $payload = $this->artistService->getEmailTemplateDefaultData($artist_id);
                    if($payload) {
                        $celebname = isset($payload['celeb_name']) ? $payload['celeb_name'] : '';
                    }
                }

                $to_support         = Config::get('product.' . env('PRODUCT') . '.mail.from_for_support');
                $to_support_address = 'support@bollyfame.com';
                $to_support_name    = 'BOLLYFAME Media';
                if($to_support) {
                    $to_support_address   = isset($to_support['address']) ? $to_support['address'] : 'support@bollyfame.com';
                    $to_support_name      = isset($to_support['name']) ? $to_support['name'] : 'BOLLYFAME Media';
                }

                // Generate Email Template specific data
                //$payload['']  = '';
                $payload['customer_email']  = $customer_email;
                $payload['customer_name']   = $customer_name;

                $payload['type']            = $type;
                $payload['feedback']        = $feedback;
                $payload['ratings']         = $ratings;
                $payload['entity']          = $entity;
                $payload['entity_id']       = $entity_id;
                $payload['platform']        = $platform;
                $payload['v']               = $platform_version;

                $payload['email_header_template']   = 'emails.' . env('PRODUCT') . '.common.header';
                $payload['email_body_template']     = 'emails.' . env('PRODUCT') . '.customer.feedback';
                $payload['email_footer_template']   = 'emails.' . env('PRODUCT') . '.common.footer';
                $payload['email_subject']           = $subject_line;
                $payload['user_email']              = $to_support_address;
                $payload['user_name']               = $to_support_name;

                // Set Send Form data
                if($customer_email) {
                    $payload['reply_to']['address'] = trim($customer_email);

                    if($customer_name) {
                        $payload['reply_to']['name'] = trim($customer_name);
                    }
                }

                $jobData = [
                    'label'     => 'CustomerFeedback',
                    'type'      => 'process_email',
                    'payload'   => $payload,
                    'status'    => 'scheduled',
                    'delay'     => 0,
                    'retries'   => 0
                ];

                // $recodset = new \App\Models\Job($jobData);
                // $recodset->save();

            }
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function sendEmail($request)
    {
        $item               = $request->all();
        $error_messages     = $results = [];
        $to_email           = (isset($item['to_email'])  && !empty($item['to_email'])) ? trim($item['to_email']) : 'contact@sherlynchopra.com';
        $to_name            = (isset($item['to_name']) && !empty($item['to_name'])) ? trim($item['to_name']) : 'contact@sherlynchopra.com';
        $bcc_emailids       = (isset($item['bcc_emailids']) && !empty($item['bcc_emailids'])) ? $item['bcc_emailids'] : [];
        $email_subject      = (isset($item['email_subject']) && !empty($item['email_subject'])) ? trim($item['email_subject']) : 'Contact Us';
        $email_message      = (isset($item['email_message']) && !empty($item['email_message'])) ? trim($item['email_message']) : 'Contact Us Message';

        $contact_email      = (isset($item['contact_email']) && !empty($item['contact_email'])) ? trim($item['contact_email']) : '';
        $contact_name       = (isset($item['contact_name']) && !empty($item['contact_name'])) ? trim($item['contact_name']) : '';
        $contact_mobile     = (isset($item['contact_mobile']) && !empty($item['contact_mobile'])) ? trim($item['contact_mobile']) : '';

        $template_data  = [];
        $template_data['contact_email']     = $contact_email;
        $template_data['contact_name']      = $contact_name;
        $template_data['contact_mobile']    = $contact_mobile;
        $template_data['message_body']      = $email_message;
        $template_data['celeb_name']        = 'Sherlyn Chopra';

        $email_template = 'emails.contactus';

        $message_data   = array(
            'user_email'    => $to_email,
            'user_name'     => $to_name,
            'bcc_emailids'  => $bcc_emailids,
            'email_subject' => $email_subject
        );

        $label      =   'contactus_email';
        $priority   =   1;

        $send_mail = $this->customermailer->common($email_template, $template_data, $message_data, $label, $priority);

        return ['error_messages' => $error_messages, 'results' => $results];
    }
}
