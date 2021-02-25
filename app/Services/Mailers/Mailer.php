<?PHP

namespace App\Services\Mailers;

use Mail, Queue, Config, View, Log, File;

abstract Class Mailer
{


    public function __construct()
    {
        // Setup product wise mail config
        $config = Config::get('product.' . env('PRODUCT') . '.mail');
        Config::set('mail',$config);
    }

    public function sendToEmail($email_template = 'emails.testemail', $template_data = [], $message_data = [], $label = '', $priority = '')
    {
        if (isset($message_data['attachments']) && !empty($message_data['attachments'])) {
        }

        // Switch SMPT Transporter depending on email template
        // Use Pepipost for sending following mails
        // 1 . Forget Password
        // 2 . Welcome
        $this->setMailTransporter($email_template);

        // Set send_form
        if(isset($template_data['send_from'])) {
            $message_data['send_from'] = $template_data['send_from'];
        }

        // Set reply_to
        if(isset($template_data['reply_to'])) {
            $message_data['reply_to'] = $template_data['reply_to'];
        }

        $send_email = Mail::send($email_template, $template_data, function ($message) use ($message_data) {

            // Set Mailer From Info: If Provide
            if(isset($message_data['send_from'])) {
                $mail_data_from_address = '';
                $mail_data_from_name    = '';
                if($message_data['send_from']) {
                    $mail_data_from_address = isset($message_data['send_from']['address']) ? $message_data['send_from']['address'] : '';
                    $mail_data_from_name    = isset($message_data['send_from']['name']) ? $message_data['send_from']['name'] : 'From';
                }
                if($mail_data_from_address){
                    $message->from($mail_data_from_address, $mail_data_from_name);
                }
            }

//            $bcc_emails = array_unique(array_merge(['sanjay.fitternity@gmail.com'], $message_data['bcc_emailids']));
            $bcc_emails = array_unique(array_merge([], $message_data['bcc_emailids']));

            $message->to($message_data['user_email'], $message_data['user_name'])->bcc($bcc_emails)->subject($message_data['email_subject']);

            if (isset($message_data['attachments']) && !empty($message_data['attachments'])) {
                foreach ($message_data['attachments'] as $attachment) {
                    $pathToFile = $attachment['path_to_file'];
                    $displayName = $attachment['display_name'];
                    $message->attach($pathToFile, ['as' => $displayName]);
                }
            }//attachments

            // Set Reply To
            if(isset($message_data['reply_to'])) {
                $mail_replay_to_address= '';
                $mail_replay_to_name   = '';

                if($message_data['reply_to']) {
                    $mail_replay_to_address = isset($message_data['reply_to']['address']) ? $message_data['reply_to']['address'] : '';
                    $mail_replay_to_name    = isset($message_data['reply_to']['name']) ? $message_data['reply_to']['name'] : 'Reply To';
                }

                if($mail_replay_to_address){
                    $message->replyTo($mail_replay_to_address, $mail_replay_to_name);
                }
            }
        });

        //Delete local files after sending emails if delete_local_files is true
        if (isset($message_data['delete_local_files']) && $message_data['delete_local_files'] == true &&
            isset($message_data['attachments']) && !empty($message_data['attachments'])
        ) {
            foreach ($message_data['attachments'] as $attachment) {
                $localpathToFile = $attachment['path_to_file'];
                if (File::exists($localpathToFile)) {
                    if (File::delete($localpathToFile)) {
                        \Log::info('Deleted Local File  ===> ' . json_encode($attachment));
                    }
                }
            }
        }

        return $send_email;
    }


    public function sendToEmailFromSupportEmail($email_template = 'emails.testemail', $template_data = [], $message_data = [], $label = '', $priority = '')
    {
        if (isset($message_data['attachments']) && !empty($message_data['attachments'])) {
        }

        // Switch SMPT Transporter depending on email template
        // Use Pepipost for sending following mails
        // 1 . Forget Password
        // 2 . Welcome
        $this->setMailTransporter($email_template);

        // Set send_form
        if(isset($template_data['send_from'])) {
            $message_data['send_from'] = $template_data['send_from'];
        }

        // Set reply_to
        if(isset($template_data['reply_to'])) {
            $message_data['reply_to'] = $template_data['reply_to'];
        }

        $send_email = Mail::send($email_template, $template_data, function ($message) use ($message_data) {

            $form_for_support       = Config::get('product.' . env('PRODUCT') . '.mail.from_for_support');
            $form_support_address   = 'support@bollyfame.com';
            $form_support_name      = 'BOLLYFAME Media';
            if($form_for_support) {
                $form_support_address   = isset($form_for_support['address']) ? $form_for_support['address'] : 'support@bollyfame.com';
                $form_support_name      = isset($form_for_support['name']) ? $form_for_support['name'] : 'BOLLYFAME Media';
            }

            // Set Mailer From Info: If Provide
            if(isset($message_data['send_from'])) {
                if($message_data['send_from']) {
                    $form_support_address   = isset($message_data['send_from']['address']) ? $message_data['send_from']['address'] : $form_support_address;
                    $form_support_name      = isset($message_data['send_from']['name']) ? $message_data['send_from']['name'] : $form_support_name;
                }
            }

            $message->from($form_support_address, $form_support_name);

//            $bcc_emails = array_unique(array_merge(['sanjay.fitternity@gmail.com'], $message_data['bcc_emailids']));
            $bcc_emails = array_unique(array_merge([], $message_data['bcc_emailids']));

            $message->to($message_data['user_email'], $message_data['user_name'])->bcc($bcc_emails)->subject($message_data['email_subject']);

            if (isset($message_data['attachments']) && !empty($message_data['attachments'])) {
                foreach ($message_data['attachments'] as $attachment) {
                    $pathToFile = $attachment['path_to_file'];
                    $displayName = $attachment['display_name'];
                    $message->attach($pathToFile, ['as' => $displayName]);
                }
            }//attachments

            // Set Reply To
            if(isset($message_data['reply_to'])) {
                $mail_replay_to_address= '';
                $mail_replay_to_name   = '';

                if($message_data['reply_to']) {
                    $mail_replay_to_address = isset($message_data['reply_to']['address']) ? $message_data['reply_to']['address'] : '';
                    $mail_replay_to_name    = isset($message_data['reply_to']['name']) ? $message_data['reply_to']['name'] : 'Reply To';
                }

                if($mail_replay_to_address){
                    $message->replyTo($mail_replay_to_address, $mail_replay_to_name);
                }
            }
        });

        //Delete local files after sending emails if delete_local_files is true
        if (isset($message_data['delete_local_files']) && $message_data['delete_local_files'] == true &&
            isset($message_data['attachments']) && !empty($message_data['attachments'])
        ) {
            foreach ($message_data['attachments'] as $attachment) {
                $localpathToFile = $attachment['path_to_file'];
                if (File::exists($localpathToFile)) {
                    if (File::delete($localpathToFile)) {
                        \Log::info('Deleted Local File  ===> ' . json_encode($attachment));
                    }
                }
            }
        }

        return $send_email;
    }


    /**
     * Calculate the number of seconds with the given delay.
     *
     * @param  \DateTime|int $delay
     * @return int
     */
    protected function getSeconds($delay)
    {

        if ($delay instanceof DateTime) {

            return max(0, $delay->getTimestamp() - $this->getTime());

        } elseif ($delay instanceof \Carbon\Carbon) {

            return max(0, $delay->timestamp - $this->getTime());

        } elseif (isset($delay['date'])) {

            $time = strtotime($delay['date']) - $this->getTime();

            return $time;

        } else {

            $delay = strtotime($delay) - time();
        }

        return (int)$delay;
    }

    /**
     * Get the current UNIX timestamp.
     *
     * @return int
     */
    public function getTime()
    {
        return time();
    }


    /**
     * Return Mail Template names for sending mail via Pepipost
     *
     *
     * @return  array
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-20
     */
    public function getPepipostMailTemplates() {
        $ret = [];

        $ret = [
            'emails.' . env('PRODUCT') . '.customer.forgetpassword',
            'emails.' . env('PRODUCT') . '.customer.welcome',
            'emails.' . env('PRODUCT') . '.customer.email_verification',
            //'emails.' . env('PRODUCT') . '.customer.customerorder',
        ];

        return $ret;
    }


    /**
     * Sets Mail Transporter according to mail template
     *
     * @param   string   $email_template
     *
     * @return  null
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-20
     */
    public function setMailTransporter($email_template) {
        $ret = true;

        // Now (2019-12-19) send all mail via Pepipost
        // Set Pepipost SMTP Config
        $config = Config::get('product.' . env('PRODUCT') . '.mail_pepipost');
        Config::set('mail',$config);

        return $ret;
    }



}
