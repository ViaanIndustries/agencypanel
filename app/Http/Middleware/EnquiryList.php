<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Session;
use Artisan;
class EnquiryList
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
		Artisan::call('view:clear');
 		$count = DB::connection('mongodb')->table('sp_enquiry')
				->where('enquiry_status','pending')
				->count();
		

 		 session(['enquiry_noti' => $count]);
        return $next($request);
    }
}
