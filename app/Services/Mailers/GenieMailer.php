<?PHP namespace App\Mailers;

use Config,Mail;

Class GenieMailer extends Mailer {


    public function processOrderPayAfterService ($data){


        $email_template =   'emails.genie.orderconfirm_payafterservice';


        $template_data  =   $data;
        $bcc_emailids   =   Config::get('mail.bcc_order');

        $message_data   = array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids'=> $bcc_emailids,
            'email_subject' => "You have received a new order | Genie Bazaar"
        );

        if(array_key_exists('delivery_challan_file',$data)){
            $message_data['attachments'] = array();
            array_push($message_data['attachments'],  ['path_to_file'=>storage_path('app/'.$data['delivery_challan_file']), 'display_name'=>'Delivery-Challan.pdf']);
        }

        // print_r($message_data);exit();

        $label = 'orderconfirm_payafterservice-G';
        $priority = 1;

        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }



    public function processOrderPayByInvoice ($data){

        $email_template =   'emails.genie.orderconfirm_paybyinvoice';

        $template_data  =   $data;
        $bcc_emailids   =   Config::get('mail.bcc_order');

        $message_data   = array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids'=> $bcc_emailids,
            'email_subject' => "You have received a new order | Genie Bazaar"
        );

        if(array_key_exists('delivery_challan_file',$data)){
            $message_data['attachments'] = array();
            array_push($message_data['attachments'],  ['path_to_file'=>storage_path('app/'.$data['delivery_challan_file']), 'display_name'=>'Delivery-Challan.pdf']);
        }

        // print_r($message_data);exit();

        $label = 'orderconfirm_paybyinvoice-G';
        $priority = 1;

        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }



    public function forgotPassword ($data){

        $email_template = 	'emails.genie.forgetpassword';
        $template_data 	= 	$data;
        $bcc_emailids 	= 	Config::get('mail.bcc_forgot_password');

        $message_data 	= array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => 'Your Password Reset Request for Genie Bazaar'
        );

        $label = 'ForgotPwd-G';
        $priority = 1;

        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }


    public function register ($data, $usertype = "genie"){

        if($usertype == "customer"){
            $email_template = 	'emails.genie.register_customer';

        }else{
            $email_template = 	'emails.genie.register_genie';
        }

        $template_data 	= 	$data;
        $bcc_emailids 	= 	Config::get('mail.bcc_register');


        $message_data 	= array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => 'Welcome to Genie Bazaar'
        );

        $label = 'Register-G';
        $priority = 1;

        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }



    public function cancelOrderItems ($data){

        $email_template =   'emails.genie.orderitems_cancel';

        $template_data  =   $data;
        $bcc_emailids   =   Config::get('mail.bcc_order');
        // $bcc_emailids   =   [];

        $cancel_by      =  (isset($data['cancel_by'])) ?  strtolower($data['cancel_by']) : '';

        if($cancel_by == 'customer'){
            $email_subject =    "Following item(s) were cancelled by customer | Genie Bazaar";
        }else{
            $email_subject =    "Following item(s) were cancelled | Genie Bazaar";
        }

        $message_data   = array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => $email_subject
        );

        if(array_key_exists('delivery_challan_file',$data) ){
            $message_data['attachments'] = array();
            array_push($message_data['attachments'], ['path_to_file'=>storage_path('app/'.$data['delivery_challan_file']), 'display_name'=>'updated-delivery-challan.pdf']);
        }

        $label = 'orderitems_cancel-G';
        $priority = 1;


        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }



    public function updateMultipleCartQuantity ($data){

        $email_template =   'emails.genie.orderitems_updated_quantity';

        $template_data  =   $data;
        $bcc_emailids   =   Config::get('mail.bcc_order');

        $email_subject =    "Order item(s) were updated | Genie Bazaar";

        $message_data   = array(
            'user_email' => $data['email'],
            'user_name' => $data['name'],
            'bcc_emailids' => $bcc_emailids,
            'email_subject' => $email_subject
        );

        if(array_key_exists('delivery_challan_file',$data) ){
            $message_data['attachments'] = array();
            array_push($message_data['attachments'], ['path_to_file'=>storage_path('app/'.$data['delivery_challan_file']), 'display_name'=>'updated-delivery-challan.pdf']);
        }

        $label = 'orderitems_cancel-G';
        $priority = 1;


        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }


    public function uploadInvoiceOrderItemsByVendor ($data){

        $email_template =   'emails.genie.upload_invoice_by_vendor';

        $template_data  =   $data;
        $bcc_emailids   =   Config::get('mail.bcc_order');

        if(isset($data['vendor_email'])){
            array_push($bcc_emailids, $data['vendor_email']);
        }

        $message_data   = array(
            'user_email'            =>  $data['email'],
            'user_name'             =>  $data['name'],
            'bcc_emailids'          =>  $bcc_emailids,
            'attachments'           =>  (isset($data['attachments'])) ? $data['attachments'] : [],
            'delete_local_files'    =>  (isset($data['delete_local_files'])) ? $data['delete_local_files'] : false,
            'email_subject'         =>  "Invoice were uploaded by vendor for following item(s) | Genie Bazaar"
        );

        $label = 'upload_invoice_by_vendor-G';
        $priority = 1;


        return $this->common($email_template, $template_data, $message_data, $label, $priority);
    }





    public function common($email_template, $template_data, $message_data, $label, $priority, $delay = 0){

        // if(env('FRONTEND_DOMAIN', 'stg.geniebazaar.com')){
        //     $message_data['user_email'] = array('sanjay.fitternity@gmail.com');
        // }
//        $message_data['user_email'] = array('anil@geniebazaar.com');

         //$message_data['user_email'] = array('ravi.baisani@gmail.com');
      // $message_data['user_email'] = array('sanjay.fitternity@gmail.com');


        return $this->sendToEmail($email_template, $template_data, $message_data, $label, $priority);

    }



}
