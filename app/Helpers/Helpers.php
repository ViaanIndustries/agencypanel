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


function print_pretty($data)
{

	echo "<pre>";
	print_r($data);
	echo "<pre>";
	//exit;

}

if (!function_exists('mongodb_start_date')) {
    function mongodb_start_date($date)
    {
        $date = ($date != '') ? $date . '00:00:00' : Config::get('app.start_date');
//        return new \DateTime( date("d-m-Y h:i:s", strtotime( $date)) );
        return new \MongoDB\BSON\UTCDateTime(strtotime($date) * 1000);
    }
}


if (!function_exists('mongodb_end_date')) {
    function mongodb_end_date($date)
    {
        $date = ($date != '') ? $date . '23:59:59' : date("d-m-Y", time()) . " 23:59:59";
//        return new \DateTime( date("d-m-Y h:i:s", strtotime($date)));
        return new \MongoDB\BSON\UTCDateTime(strtotime($date) * 1000);
    }
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

function hyphen_date($date)
{
    return str_replace("/", "-", $date);
}
?>
