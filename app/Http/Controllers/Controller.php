<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Response;
use Validator;
use Illuminate\Validation\Rule;
use Config;
use \Firebase\JWT\JWT;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $statusCode = 200;

    protected $jwtauth;

    public function __construct(){

    }


    public function getStatusCode (){
        return $this->statusCode;
    }

    public function setStatusCode ($statusCode){
        $this->statusCode = $statusCode;
        return $this;
    }

    public function responseNotFound ($message = 'Not Found'){
        return   $this->setStatusCode(404)->respondWithError($message);
    }


    public function responseJsonEmpty ($message = 'No Result'){
        return   $this->setStatusCode(200)->respondWithError($message);
    }



    public function validateRequestData($requestData, $rules){

        $validator  =   Validator::make($requestData,$rules);

        if ( $validator->fails() ) {
            $errors             =   $validator->errors();
            $errors             =   json_decode(json_encode($errors));
            $messages           =   array();

            foreach ($errors as $key => $value) {
                $messages[$key] = $value[0];
            }

            $errorData = ['error' => true, 'error_messages' => array_values($messages), 'status_code' => 400];
            return $errorData;
        }

    }



    public function respondWithError ($error_messages = [], $statusCode = 400){

        $statusCode = 400;
        return response()->json(
            [
                'error' => true,
                'data'  => null,
                'error_messages' => $error_messages,
                'status_code' => ($statusCode != 200) ? $statusCode : 400
            ],
            $statusCode);
    }


    public function responseJson ($responseData, $statusCode = 200){

        if(isset($responseData['data']) && !empty($responseData['data']['error_messages'])) {
//            $statusCode = 400;
            $errorData = [
                'error' => true,
                'data'  => null,
                'error_messages' => $responseData['data']['error_messages'],
                'status_code' => ($statusCode != 400) ? $statusCode : 400
            ];
            return response()->json($errorData, $statusCode);
        }

        $responseData['error']          =   (isset($responseData['error']) && $responseData['error'] === true) ? $responseData['error'] : false;
        $responseData['status_code']    =   $statusCode;
        $responseData['data']           =   (isset($responseData['data']) && !empty($responseData['data']) && isset($responseData['data']['results']) ) ? $responseData['data']['results'] : null;

        if(isset($responseData['data']) &&  isset($responseData['data']['error_messages'])){
            unset($responseData['data']['error_messages']);
        }

        return response()->json($responseData, $statusCode);

    }


    public function validateToken()
    {

        $jwt_token = request()->header('Authorization');

        if(isset($jwt_token) && !empty($jwt_token) && $jwt_token != ''){

            try{

                $jwt_key            =   Config::get('jwt.login.key');
                $jwt_alg            =   Config::get('jwt.login.alg');
                $decodedToken       =   \Firebase\JWT\JWT::decode($jwt_token, $jwt_key, array($jwt_alg));
                $decoded            =   json_decode(json_encode($decodedToken), True);
                $customer_id        =   ($decoded && isset($decoded['user']) && isset($decoded['user']['_id'])) ? : "";
                $customer           =   \App\Models\Customer::where('_id','=', $customer_id)->first();

                if($customer && isset($customer['status']) && in_array($customer['status'] , ['banned']) ){
                    return response()->json(['error' => true, 'message' => 'Account suspended', 'status_code' => 401], 401);
                }

            }catch(\Firebase\JWT\DomainException $e){

                return response()->json(['error' => true, 'message' => 'Token incorrect', 'status_code' => 400], 400);

            }catch(\Firebase\JWT\ExpiredException $e){

                return response()->json(['error' => true, 'message' => 'Token expired', 'status_code' => 401], 401);

            }catch(\Firebase\JWT\SignatureInvalidException $e){

                return response()->json(['error' => true, 'message' => 'Signature verification failed', 'status_code' => 402], 402);

            }catch(\Exception $e){

                return response()->json(['error' => true, 'message' => 'Token incorrect', 'status_code' => 403], 403);
            }
        }

    }


}
