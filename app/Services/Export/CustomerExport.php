<?php

namespace App\Services\Export;

use config;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Excel;

class CustomerExport
{


    public function export_customer()
    {


        $customers = \App\Models\Customer::where('mobile_country_code', 91)->skip(0)->limit(10)->get();;

        if (sizeof($customers) > 0) {

            \Excel::create('Order', function ($excel) use ($customers) {

                $excel->sheet('order', function ($sheet) use ($customers) {

                    $excelData = [];
                    foreach ($customers as $key => $value) {
                        $orderexcel = [
                            'first_name' => isset($value['first_name']) ? $value['first_name'] : '-',
                            'last_name' => isset($value['last_name']) ? $value['last_name'] : '-',
                            'email' => isset($value['email']) ? $value['email'] : '-',
                            'email_verified' => isset($value['email_verified']) ? $value['email_verified'] : '-',
                            'mobile_verified' => isset($value['mobile_verified']) ? $value['mobile_verified'] : '-',
                            'status' => isset($value['status']) ? $value['status'] : '-',
                            'mobile' => isset($value['mobile']) ? $value['mobile'] : '-',
                            'platform' => isset($value['platform']) ? $value['platform'] : '-',
                        ];

                        array_push($excelData, $orderexcel);
                    }
                    $sheet->fromArray($excelData);
                });

            })->export('csv');
        }
        return true;
    }


}
