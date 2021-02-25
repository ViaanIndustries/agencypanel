<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use ReceiptValidator\GooglePlay\Validator as PlayValidator;
use ReceiptValidator\iTunes\Validator as iTunesValidator;
use ReceiptValidator\iTunes\Response as ValidatorResponse;
class TransactionService
{
    protected $repObj;
    protected $badge;

 

    public function __construct()
    {

    }
    public function approve($requestData)
    {
    	$error_messages     =   $results = [];
    	// package name ='com.poonampandey';
    	// product_id ='com.poonampandey_69coins'
    	if($requestData['platform'] == 'android')
    	{
    	$scope = ['https://www.googleapis.com/auth/androidpublisher'];

		$configLocation =config_path().'/razrcorp_service_account.json';
		
    	$client = new \Google_Client();
    	
		$client->setApplicationName('test');
		$client->setAuthConfig($configLocation);
		$client->setScopes($scope);
		$validator = new PlayValidator(new \Google_Service_AndroidPublisher($client));

		//print_r($validator);exit;
        $purchaseToken  = (isset($requestData['purchase_key']) && $requestData['purchase_key'] != '') ? $requestData['status'] : '';
        $productId  = (isset($requestData['package_sku']) && $requestData['package_sku'] != '') ? $requestData['package_sku'] : '';
        $packageName  = (isset($requestData['package_name']) && $requestData['package_name'] != '') ? $requestData['package_name'] : '';

		$purchaseToken ='elegncbajeionhkjkpgdhbig.AO-J1OwzlKEwtx80bwni6rF5xxYMRWYYzqDTatpW0Jo7DbNCnwMU0eHiwrAxZ4QXJd_4ObgKowJWoLJjB1QJRNoYs5VqMv3KkMFQmtMyeENm36Us8o8Ezd6tFbTwwzOQSmePUt6t6SwC1X9hvRNPBUpchHjthnohbQ';

		$packageName 	= 'com.razrcorp.zareenkhan';
		$productId 		= 'com.razrcorp.zareenkhan_69_coins';
		
		try {
		    $response = $validator->setPackageName($packageName)
		    ->setProductId($productId)
		    ->setPurchaseToken($purchaseToken)
		    ->validatePurchase();

		   print_r($response);exit; 
		    
		} catch (\Exception $e) {
		  echo 'got error = ' . $e->getMessage() . PHP_EOL;
		}

   	  }
    elseif($requestData['platform']=='ios')
	   {
	   	$newcontent ='';
	   	$receiptBase64Data  = (isset($requestData['receipt']) && $requestData['receipt'] != '') ? $requestData['receipt'] : '';

	   	$receiptBase64Data = "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";//base64_encode($receipt);
	   //	echo $converted_receipt;
	   	$validator = new iTunesValidator(iTunesValidator::ENDPOINT_PRODUCTION);
	   	$response = null;
			try {
			  $response = $validator->setReceiptData($receiptBase64Data)->validate();
			} catch (\Exception $e) {
			  echo 'got error = ' . $e->getMessage() . PHP_EOL;
			}


		if ($response instanceof ValidatorResponse && $response->isValid()) {
		  echo 'Receipt is valid.' . PHP_EOL;
		  echo 'getBundleId: ' . $response->getBundleId() . PHP_EOL;
		  foreach ($response->getPurchases() as $purchase) {
		    echo 'getProductId: ' . $purchase->getProductId() . PHP_EOL;
		    echo 'getTransactionId: ' . $purchase->getTransactionId() . PHP_EOL;
		    if ($purchase->getPurchaseDate() != null) {
		      echo 'getPurchaseDate: ' . $purchase->getPurchaseDate()->toIso8601String() . PHP_EOL;
		    }
		  }
		} else {
		  echo 'Receipt is not valid.' . PHP_EOL;
		  echo 'Receipt result code = ' . $response->getResultCode() . PHP_EOL;
		}
	   }

	}

	// public function getReceiptData($receipt)
	// {
	// 	$fh = fopen('showme.txt',w);
	// 	fwrite($fh,$receipt);
	// 	fclose($fh);
	// 	$endpoint = 'https://sandbox.itunes.apple.com/verifyReceipt';
		
	// 	$ch = curl_init($endpoint);
	// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// 	curl_setopt($ch, CURLOPT_POST, true);
	// 	curl_setopt($ch, CURLOPT_POSTFIELDS, $receipt);
	// 	$response = curl_exec($ch);
	// 	$errno = curl_errno($ch);
	// 	$errmsg = curl_error($ch);
	// 	curl_close($ch);
	// 	$msg = $response.' - '.$errno.' - '.$errmsg;
	// 	echo $response;
	// }
}