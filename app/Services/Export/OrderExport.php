<?php

namespace App\Services\Export;

use App\Services\OrderService;


use config;

class OrderExport
{
    protected $artistservice;

    public function __construct(OrderService $orderservice)
    {
        $this->orderservice = $orderservice;
    }

    public function export_order($data)
    {
        $order_info = $this->orderservice->index($data);

        $order['orders'] = $order_info['orders'];
        $order['appends_array'] = $order_info['appends_array'];

        if (sizeof($order) > 0) {

            \Excel::create('Order', function ($excel) use ($order) {

                $excel->sheet('order', function ($sheet) use ($order) {

                    $excelData = [];
                    foreach ($order['orders'] as $key => $value) {

                        $orderexcel = [
                            'package_name' => isset($value['package']) && isset($value['package']['name']) ? $value['package']['name'] : '-',
                            'currency_code' => isset($value['currency_code']) ? $value['currency_code'] : '-',
                            'transaction_price' => isset($value['transaction_price']) ? $value['transaction_price'] : '-',
                            'vendor' => isset($value['vendor']) ? $value['vendor'] : '-',
                            'customer_name' => isset($value['customer']) && isset($value['customer']['first_name']) ? $value['customer']['first_name'] : '-',
                            'customer_email' => isset($value['customer']) && isset($value['customer']['email']) ? ($value['customer']['email']) : '-',
                            'artist_name' => isset($value['artist']) && isset($value['artist']['first_name']) ? $value['artist']['first_name'] . ' ' . $value['artist']['last_name'] : '-',
                            'package_sku' => isset($value['package_sku']) ? $value['package_sku'] : '-',
                            'package_coins' => isset($value['package_coins']) ? $value['package_coins'] : '-',
                            'package_xp' => isset($value['package_xp']) ? $value['package_xp'] : '-',
                            'package_price' => isset($value['package_price']) ? $value['package_price'] : '-',
                            'platform' => isset($value['platform']) ? $value['platform'] : '-',
                            'order_id' => isset($value['order_id']) ? $value['order_id'] : '-',
                            'order_status' => isset($value['order_status']) ? $value['order_status'] : '-',
                            'purchase_key' => isset($value['purchase_key']) ? $value['purchase_key'] : '-',
                            'vendor_order_id' => isset($value['vendor_order_id']) ? $value['vendor_order_id'] : '-',
                            'created_at' => isset($order['appends_array']['created_at']) ? $order['appends_array']['created_at'] : '-',
                            'created_at_end' => isset($order['appends_array']['created_at_end']) ? $order['appends_array']['created_at_end'] : '-',
                        ];

                        array_push($excelData, $orderexcel);
                    }
                    $sheet->fromArray($excelData);
                });

            })->download('xlsx');
        }
        return true;
    }
}