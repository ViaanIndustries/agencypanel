<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Input;
use Redirect;
use Config;
use Session;
use Validator;
use Hash;

use App\Http\Controllers\Controller;

//use App\Services\CmsuserService;


class AuthController extends Controller
{

    protected $cmsuserService;
    protected $greeting_msg = '';
    protected $quote = '';
    protected $quote_arr = [
        'When there is no desire, all things are at peace.',
        'Simplicity is the ultimate sophistication.',
        'Simplicity is the essence of happiness.',
        'Smile, breathe, and go slowly.',
        'Simplicity is an acquired taste.',
        'Well begun is half done.',
        'He who is contented is rich.',
        'Very little is needed to make a happy life.'
    ];


    public function __construct()
    {
       // $this->cmsuserservice = $cmsuserService;
        $this->setGreeting();
        $this->setQuote();
    }


    public function setGreeting()
    {
        $hour = date('H');
        $dayTerm = ($hour > 17) ? "Evening" : ($hour > 12) ? "Afternoon" : "Morning";
        $this->greeting_msg = "Good " . $dayTerm;
    }


    public function setQuote()
    {
        $this->quote = $this->quote_arr[array_rand($this->quote_arr)];
    }


    public function cmsUserLogin(Request $request)
    {

         $viewdata = [];
        return view('admin.auth.login_v1', $viewdata);
    }


    public function doCmsUserLogin(Request $request)
    {

          //Pre Validation check
        $error_messages = [];
        $data = $request->all();

        $rules = array('email' => 'required|email', 'password' => 'required');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
           // Redirect::back()->withErrors($validator)->withInput(Input::except('password'));
        }


        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        $cmsuser = \App\Models\Agency::where('email', $email)->where('status',"active")->first();
         if (!$cmsuser) {
            $error_messages['message'] = 'The Email You Provided Does Not Exist';
        } else {
            if (!Hash::check($password, $cmsuser->password)) {
                $error_messages['message'] = 'Invalid credentials, please try again';
            }
        }

        if (count($error_messages) > 0) {
             return Redirect::back()->withErrors(['error_messages'=>$error_messages]);

        }


        if (empty($error_messages)) {


            $cmsuserArr = $cmsuser->toArray();
            Session::put('agency_id', $cmsuser->_id);
            Session::put('user_id', $cmsuser->_id);

             Session::put('user_first_name', $cmsuser->owner_name);
            Session::put('user_last_name', $cmsuser->owner_name );
            Session::put('user_email', $cmsuser->email);
             Session::put('greeting_msg', $this->greeting_msg);
            Session::put('quote', $this->quote);
            Session::flash('message', 'Login succesfully');
            return Redirect::route('admin.home.dashboard');
        }

    }


    public function logout(Request $request)
    {
        Session::flush();

    //    return Redirect::route('admin.auth.showlogin');
       return Redirect::to('/');

    }


}

