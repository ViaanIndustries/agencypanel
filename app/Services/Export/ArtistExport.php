<?php

namespace App\Services\Export;

use config;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Excel;
use Session;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\FromQuery;
 
class ArtistExport implements FromCollection, WithHeadings
{ 
    protected $data;

 function __construct($data) {
        $this->data = $data;

 }


public function collection()
{
    $requestData = $this->data;
    $perpage = null;
    $results= [];
    $perpage= 15;
    $name   = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
    $email  = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
    $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
    $sort = (isset($requestData['sort']) && $requestData['sort'] != '') ? $requestData['sort'] : '';
    $agency_id =Session::get('agency_id');
    $appends_array = array('name' => $name, 'email' => $email, 'status' => $status, 'sort'=>$sort);
    $query = \App\Models\Cmsuser::with('artistconfig');//->orderBy('first_name')->orderBy('last_name');
    $query->where('agency', $agency_id);

    if ($name != '') {
        $query->where(function($q) use ($name) {
            $q->orWhere('first_name', 'LIKE', '%' . $name . '%')->orWhere('last_name', 'LIKE', '%' . $name . '%');
        });
    }

    if ($email != '') {
        $query->where('email', 'LIKE', '%' . $email . '%');
    }

    if ($status != '') {
        $query->where('status', $status);
    }
 if ($sort != '') {
        if($sort =='coins') //Most Popular
        $query->orderBy('stats.coins' , 'desc');
        if($sort =='name')
        $query->orderBy('first_name' , 'asc');
        if($sort =='followers')
        $query->orderBy('stats.followers' , 'desc');
    }

        return $query->get(['first_name','last_name','email','mobile','city','gander','coins','stats']);
    
}


public function headings(): array
{
    return [
        'first_name',
        'last_name',
        'email',
        'mobile',
        'city',
        'gender',
        'coins',
        'stats',

    ];
}

}
