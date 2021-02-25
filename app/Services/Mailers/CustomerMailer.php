<?PHP

namespace App\Services\Mailers;

use Config, Mail;

Class CustomerMailer extends Mailer
{
    private $product    = 'apm';
    private $app_name   = 'BOLLYFAME';

    public function __construct() {
        parent::__construct();

        $this->product = env('PRODUCT');

        $this->product_name = Config::get('product.' . env('PRODUCT') . '.app_name');
    }

    public function common($email_template, $template_data, $message_data, $label, $priority, $delay = 0)
    {
        return $this->sendToEmail($email_template, $template_data, $message_data, $label, $priority);
    }

    public function forgotPassword($data)
    {
        $email_template = 'emails.' . $this->product . '.customer.forgetpassword';

        $bcc_emailids = Config::get('mail.bcc_forgot_password');

        $message_data = array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'user_password' => $data['password'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => 'Your Password Reset Request for ' . $this->product_name,
        );

        $label = 'forgotpassword_c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

    public function emailfororder($data)
    {
        $email_template = $data['email_body_template'];

        $bcc_emailids = $data['bcc_emailids'];

        $message_data = array(
            'user_email' => $data['customer_email'],
            'user_name' => $data['customer_name'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => 'Your order has been submited',
        );

        $label = 'email_for_order-c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }


    /**
     * Send OTP Mail to customer
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-12
     */
    public function sendOtp($data)
    {
        $email_template = 'emails.' . $this->product . '.customer.email_verification';

        $otp = $data['otp'];
        $message_data = array(
            'user_email' => $data['customer_email'],
            'user_name' => $data['customer_name'],
            'bcc_emailids' => [],
            'otp' => $data['otp'],
            'email_subject' => $otp . ' is your OTP to veify email on BollyFame Account',
        );

        $label = 'email_for_otp-c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

    /**
     * Send Welcome Mail to customer
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-12
     */
    public function sendWelcome($data)
    {
        $email_template = 'emails.' . $this->product . '.customer.welcome';

        $message_data = array(
            'user_email'    => $data['customer_email'],
            'user_name'     => $data['customer_name'],
            'bcc_emailids'  => [],
            'email_subject' => 'Welcome to ' . $this->product_name,
            'celeb_name'    => 'BollyFame',
        );

        $label = 'email_for_welcome-c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

    /**
     * Send Forgot Password Mail to customer
     *
     * @param   array $data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-06-12
     */
    public function sendForgotPassword($data)
    {
        $email_template = 'emails.' . $this->product . '.customer.forgetpassword';

        $message_data = array(
            'user_email' => $data['customer_email'],
            'user_name' => $data['customer_name'],
            'password' => $data['password'],
            'bcc_emailids' => [],
            'email_subject' => 'Your Password Reset Request for ' . $this->product_name,
        );

        $label = 'forgotpassword_c';
        $priority = 1;

        return $this->common($email_template, $data, $message_data, $label, $priority);
    }

}
