<?php

namespace App\Services\Export;

use config;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Excel;
use Session;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\FromQuery;
use App\Models\Cmsuser;
use App\Models\Live; 
use Maatwebsite\Excel\Concerns\FromArray;

class ArtistLiveSessionExport implements FromArray, WithHeadings
{ 
    protected $data;

 function __construct($data) {
        $this->data = $data;

 }

 public function array(): array
 {
     $data =  $this->export_live_session();
     $list = [];
     $finaldata = [];
     foreach($data as $key => $value)
     {
        $list['first_name'] = $value['artist']['first_name'];
        $list['last_name'] = $value['artist']['first_name'];
        $list['mobile'] = $value['artist']['mobile'];
        $list['email'] = $value['artist']['email'];
        $list['live_session_entry'] = $value['artist']['coins'];
        $list['start_at'] = $value['start_at'];
        $list['end_at'] = $value['end_at'];
        $list['total_views'] = $value['stats']['views'];
        $list['total_gifts'] = $value['stats']['gifts'];
        $list['doller_rate_per_coin'] = $value['doller_rate'];

        $list['total_coins_earned'] = $value['stats']['coin_spent'];

        $list['total_earning_doller'] = $value['total_earning_doller'];


        $finaldata[] = $list;


     }
     return $finaldata;
 }


public function export_live_session()
{
    $requestData = $this->data;
    $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
    $agency_id = $requestData['agency_id'];
    $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
    $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
    $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
    $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';

    $artist_role_ids = \App\Models\Role::where('slug', 'artist')->where('artist_id','<>',"5d3ee748929d960e7d388ee2")->pluck('_id');
    $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];


    $artist_list = Cmsuser::where('agency', $agency_id)->whereIn('roles', $artist_role_ids)->pluck('_id');
    $query = Live::where('is_refund', '<>', true)->with(array('artist' => function ($q) {
          $q->select('first_name','last_name','mobile','email','coins');
    }));
    if (!empty($artist_id)) {
        $query->where('artist_id', $artist_id);
    } else {
        $query->whereIn('artist_id', $artist_list);
    }
    if ($created_at != '') {
        $query->where('start_at', '>', mongodb_start_date($created_at));
    }

    if ($created_at_end != '') {
        $query->where('start_at', '<', mongodb_end_date($created_at_end));
    }
    if ($sort == 'coins') {
        $query->orderby('stats.coin_spent', 'DESC');
    } else if ($sort == 'views')
    {
        $query->orderby('stats.views', 'DESC');
    }else if($sort == 'gifts')
    {
        $query->orderby('stats.gifts', 'DESC');
    }
    return  $query->get()->toArray();
}


public function headings(): array
{
    return [
        'name',
        'email',
        'mobile',
        'email',
        'live session entry fee',
        'start_at',
        'end_at',
        'total_view',
        'total_gifts',
        'Doller Rate Per Coins',
        'total_coins_earned',

        'total_earning_in_doller',

    ];
}

}
