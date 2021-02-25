<?php
 
if (!function_exists('apply_cloudfront_url')) {
    /**
     * Returns cloudfront url
     *
     * */
    function apply_cloudfront_url($subject)
    {

	  
      
	    //###### S3 ENDPOINTS
	    $search  = Config::get('cloudfront' . '.cloudfront.urls.s3_endpoint');
  
	    //###### NEW CF ENDPOINTS
	    $replace = Config::get('cloudfront' . '.cloudfront.urls.cf_endpoint');
	
	    $string_subject = json_encode($subject);  
	    $modified_subject = str_replace($search, $replace, $string_subject);
	    return json_decode($modified_subject, true);
    }
}

if (!function_exists('env_cache')) {
    function env_cache($key)
    {
        $product = env('PRODUCT', 'localKHEL');
        $env = env('APP_ENV', 'local');

        return strtolower(trim($product . ':' . $env . ':' . $key));
    }
}


function printme($data)
{

	echo "<pre>";
	print_r($data);
	echo "<pre>";
	exit;

}

function apply_cloudfront_url($subject)
{

    //###### S3 ENDPOINTS
    $search = Config::get('product.' . env('PRODUCT') . '.cloudfront.urls.s3_endpoint');

    //###### NEW CF ENDPOINTS
    $replace = Config::get('product.' . env('PRODUCT') . '.cloudfront.urls.cf_endpoint');

    $string_subject = json_encode($subject);
    $modified_subject = str_replace($search, $replace, $string_subject);

    return json_decode($modified_subject, true);
}
?>
