<?php

namespace App\Services;

use Session;
use Redirect;
use Input;
use Crypt;
use Hash;
use Validator;
use Config;
use Request;
use \Firebase\JWT\JWT;


use \Customer;
use \Cmsuer;



Class Jwtauth {


    public function createLoginToken($user){

//        $userArr                        =   (is_array($user)) ? $user : $user->toArray();
        $userArr                        =   $user;
        $userData                       =   [];
        $userData['_id']                =   (isset($userArr['_id']))            ?   $userArr['_id']             : "";
        $userData['first_name']         =   (isset($userArr['first_name']))     ?   $userArr['first_name']      : "";
        $userData['last_name']          =   (isset($userArr['last_name']))      ?   $userArr['last_name']       : "";
        $userData['email']              =   (isset($userArr['email']))          ?   $userArr['email']           : "";
        $userData['identity']           =   (isset($userArr['identity']))       ?   $userArr['identity']        : "";
        $userData['status']             =   (isset($userArr['status']))         ?   $userArr['status']          : "";
        $userData['platform']           =   (request()->header('platform')) ? trim(request()->header('platform')) : "";
        $userData['env']                =   env('APP_ENV', 'production');
        $userData['token_version']      =   Config::get('jwt.token_version');

        // return $userData;
        $jwt_payload = array(
            "iat"       =>  Config::get('jwt.login.iat'),
            "nbf"       =>  Config::get('jwt.login.nbf'),
            "exp"       =>  Config::get('jwt.login.exp'),
            "user"      =>  $userData
        );

        $jwt_key    =   Config::get('jwt.login.key');
        $jwt_alg    =   Config::get('jwt.login.alg');
        $token      =   JWT::encode($jwt_payload, $jwt_key, $jwt_alg);

        return $token;
    }



    public function decodeLoginToken($jwt_token){


        $decodedTokenArrOnErr   = ['error' => true, 'user' => ['_id' => 'xxxxx', 'name' => 'error', 'email' => 'error@gmail.com']];
        $jwt_key                = Config::get('jwt.login.key');
        $jwt_alg                = Config::get('jwt.login.alg');

        try{
            $decodedToken       =   \Firebase\JWT\JWT::decode($jwt_token, $jwt_key, array($jwt_alg));
            $decodedTokenArr    =   json_decode(json_encode($decodedToken), True);
//            $customer_id        =  ($decodedTokenArr && isset($decodedTokenArr['user']) && isset($decodedTokenArr['user']['_id'])) ? : "";
//            $customer           =   \App\Models\Customer::where('_id','=', $customer_id)->first();
//
//            if($customer && isset($customer['status']) && in_array($customer['status'] , ['banned']) ){
//                array_set($decodedTokenArrOnErr, 'user.message', 'Account suspended');
//                array_set($decodedTokenArrOnErr, 'user.status_code', 401);
//                return $decodedTokenArrOnErr;
//            }

        }catch(\Firebase\JWT\DomainException $e){
            array_set($decodedTokenArrOnErr, 'user.message', 'Token incorrect');
            array_set($decodedTokenArrOnErr, 'user.status_code', 401);
            return $decodedTokenArrOnErr;

        }catch(\Firebase\JWT\ExpiredException $e){
            array_set($decodedTokenArrOnErr, 'user.message', 'Token expired');
            array_set($decodedTokenArrOnErr, 'user.status_code', 401);
            return $decodedTokenArrOnErr;

        }catch(\Firebase\JWT\SignatureInvalidException $e){
            array_set($decodedTokenArrOnErr, 'user.message', 'Signature verification failed');
            array_set($decodedTokenArrOnErr, 'user.status_code', 402);
            return $decodedTokenArrOnErr;

        }catch(\Exception $e){
            array_set($decodedTokenArrOnErr, 'user.message', 'Token incorrect');
            array_set($decodedTokenArrOnErr, 'user.status_code', 403);
            return $decodedTokenArrOnErr;
        }

        return $decodedTokenArr;
    }




    public function customerIdFromToken(){

        $jwt_token                  =   Request::header('Authorization');
        $decoded_token              =   $this->decodeLoginToken($jwt_token);
        $customerArr                =   $decoded_token['user'];
        $customer_id                =   trim($customerArr['_id']);

        return $customer_id;
    }



    public function customerFromToken(){

        $jwt_token                  =   Request::header('Authorization');
        $decoded_token              =   $this->decodeLoginToken($jwt_token);
        $customerArr                =   $decoded_token['user'];

        return $customerArr;
    }







}