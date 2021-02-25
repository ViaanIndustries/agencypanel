<?php

namespace App\Http\Requests\Api;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use App\Traits\ApiResponseTrait as ApiResponse;


abstract class FormRequest extends LaravelFormRequest
{
	use ApiResponse;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    abstract public function rules();
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    abstract public function authorize();
    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

  
        $errors = $this->trasformErrorObjectToArr($errors);
        
        throw new HttpResponseException(

        	response()->json(
            [
                'error' => false,
                'error_messages' => $errors,
                'data'  => null,
                'message'  => null,
                'status_code' => JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY)

        );
        

    }





}