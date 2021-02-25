<?php

namespace App\Traits;


use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseTrait
{
    protected $statusCode = Response::HTTP_OK;

    public function getStatusCode () {
        return $this->statusCode;
    }

    public function setStatusCode ($statusCode) {
        $this->statusCode = $statusCode;
        return $this;
    }


    public function validateRequestData($requestData, $rules) {

        $validator  =   Validator::make($requestData,$rules);

        if ( $validator->fails() ) {
            $errors             =   $validator->errors();
            $errors             =   json_decode(json_encode($errors));
            $messages           =   array();

            foreach ($errors as $key => $value) {
                $messages[$key] = $value[0];
            }

            $errorData = ['error' => true, 'error_messages' => array_values($messages), 'status_code' => Response::HTTP_BAD_REQUEST];
            return $errorData;
        }

    }


     public function trasformErrorObjectToArr($errors) {

            $messages           =   array();

            if(!empty($errors) && null !== $errors){
                foreach ($errors as $key => $value) {
                    $messages[$key] = $value[0];
                }
            }
        
            return $messages;
    }


    public function responseJson($responseData, $statusCode = Response::HTTP_OK) {

        if(isset($responseData['data']['error_messages']) && !empty($responseData['data']['error_messages'])) {
            return $this->respondWithError($responseData['data']['error_messages']);
        }

        $data       =   (isset($responseData['data']) && !empty($responseData['data']) && isset($responseData['data']['results']) ) ? $responseData['data']['results'] : null;
        $message    =   (isset($responseData['message']) && !empty($responseData['message']) ) ? $responseData['message'] : null;


       return $this->respondWithSuccess($data, $statusCode, $message);
    }


    public function respondWithError ($error_messages = [], $statusCode = Response::HTTP_BAD_REQUEST) {

        return response()->json(
            [
                'error' => true,
                'error_messages' => $error_messages,
                'data'  => null,
                'status_code' => $statusCode
            ],
            $statusCode);
    }

    public function respondWithSuccess ($data = [], $statusCode = Response::HTTP_OK, $message = null) {


        return response()->json(
            [
                'error' => false,
                'error_messages' => null,
                'data'  => $data,
                'message'  => $message,
                'status_code' => $statusCode
            ],
            $statusCode);
    }

}
