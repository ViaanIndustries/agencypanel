<?php


namespace App\Services\ArtistMis;

use App\Models\Passbook;
use App\Models\MisPurchase;

/**
 * 
 */	
class PurchaseService {

    public $data = [];
    public $date = [];
    public $package_sku = [];
    public $payment_gateway = [];
    public $platform = [];

    public $artists = [
        '5d35a9cd6338905b1e33f9f2'=>'Aabha Paul',
        '5cda8e156338905d962b9472'=>'Anveshi Jain',
        '5dbfc35f6338900954496202'=>'Gehana Vasisth',
        '5c9a21aa633890355a377d33'=>'Sherlyn Chopra',
        '5d70adba63389019631c90d2'=>'Simran K',
        '5a9d91aab75a1a17711af702'=>'Scarlett Rose',
    ];

	public function __construct(){

	}

  	public function coinsAdded($request){

  		$passbook = Passbook::raw(function($collection) use ($request){

            $match['$match'] = [
            	'created_at'=>[
            		'$gte'=> mongodb_converted_date($request['date'].' 00:00:00'),
                    '$lte' => mongodb_converted_date($request['date'].' 23:59:59')
            	],
            	'txn_type' => !empty($request['txn_type']) ?  $request['txn_type'] : 'added',
            	'status'=> !empty($request['status']) ?  $request['status'] : 'success'
            ];

            if(!empty($request['artist_id'])){
                $match['$match']['artist_id'] = $request['artist_id'];
            }

            $group['$group'] = [
            	'_id'   => [
                    'date' => [
                        '$dateToString' => ['format' => '%Y-%m-%d %H:00:00', 'date' => '$created_at',"timezone" => "Asia/Kolkata" ]
                    ],
                    'artist_id' => '$artist_id',
                    'platform'=>'$platform',
                    'payment_gateway'=>'$txn_meta_info.vendor',
                    'package_sku'=>'$txn_meta_info.package_sku'
                ],
                'count' => [
                    '$sum' => 1
                ],
                'coins' => [
                    '$sum' => '$coins'
                ],
                'amount' => [
                    '$sum' => '$amount'
                ],
            ];

            $project['$project'] = [
                '_id'=> '-1',
                'artist_id' => '$_id.artist_id',
                'platform'=>'$_id.platform',
                'payment_gateway'=>'$_id.payment_gateway',
                'package_sku'=>'$_id.package_sku',
                'date'=>'$_id.date',
                'count'=>'$count',
                'coins'=>'$coins',
                'amount'=>'$amount'
            ];

            $aggregate = [
                $match,
                $group,
                $project
            ];

            return $collection->aggregate($aggregate); 

        });


        // echo'<pre>';print_r($passbook->toArray());exit();

        foreach ($passbook->toArray() as $key => $value) {

            $data_key = $value['artist_id'].'-'.$value['date'];
            $artist_id = $value['artist_id'];

            $this->setArtistStructure($data_key,$value);
            $this->setStructure('platform',$data_key,$value);
            $this->setStructure('payment_gateway',$data_key,$value);
            $this->setStructure('package_sku',$data_key,$value);

        }

        foreach ($this->data as &$value) {
            $value['platform'] = array_values($value['platform']);
            $value['payment_gateway'] = array_values($value['payment_gateway']);
            $value['package_sku'] = array_values($value['package_sku']);
        }


        echo'<pre>';print_r($this->data);exit();

  		


  	}

    public function setArtistStructure($key,$value){

        if(empty($this->data[$key])){

            $this->data[$key] = [
                'artist_id'=>$value['artist_id'],
                'coins'=>$value['coins'],
                'amount'=>$value['amount'],
                'count'=>$value['count'],
                'mis_datetime'=>date("Y-m-d 00:00:00", strtotime($value['date'])),
                'start_datetime' => $value['date'],
                'end_datetime' => date("Y-m-d H:i:s", strtotime($value['date'].'+1 hour')),
                'start_end_hour' => date("ha",strtotime($value['date'])).'-'.date("ha", strtotime($value['date'].'+1 hour'))
            ];

        }else{

            $this->data[$key]['coins'] += $value['coins']; 
            $this->data[$key]['amount'] += $value['amount'];
            $this->data[$key]['count'] += $value['count'];
        }

    }

    public function setStructure($type,$key,$value){

        if(empty($this->data[$key][$type][$value[$type]])){

            $this->data[$key][$type][$value[$type]] = [
                'type'=>$value[$type],
                'coins'=>$value['coins'],
                'amount'=>$value['amount'],
                'count'=>$value['count']
            ];

            if($type == 'package_sku'){
                $this->data[$key][$type][$value[$type]]['type'] .= '-'.$value['platform'];
            }

        }else{
            $this->data[$key][$type][$value[$type]]['coins'] += $value['coins'];
            $this->data[$key][$type][$value[$type]]['amount'] += $value['amount'];
            $this->data[$key][$type][$value[$type]]['count'] += $value['count'];
        }

    }

    public function getPurchseData($request){

        $bucket_array = [
            'hourly',
            'date_wise',
            'platform',
            'payment_gateway',
            'package_sku'
        ];

        $query = MisPurchase::where('mis_datetime','>=',mongodb_converted_date($request['start_date']))
            ->where('mis_datetime','<',mongodb_converted_date($request['end_date']));

        if(!empty($request['artist_ids']) && is_array($request['artist_ids'])){
            $query->whereIn('artist_id',$request['artist_ids']);
        }

        $misPurchse = $query->orderBy('_id','desc')->get()->toArray();

        $bucket = $request['bucket'];

        if(!empty($misPurchse)){

            $this->data = [
                'count'=>0,
                'coins'=>0,
                'amount'=>0
            ];

            switch ($bucket) {
                case 'hourly':
                    $this->data[$bucket] = $this->getHourlyObject();
                    break;
                case 'date_wise':
                    $this->data[$bucket] = $this->getDateWiseObject($request);
                    break;
            }

           
            foreach ($misPurchse as $value) {

                $this->data['count'] += $value['count'];
                $this->data['coins'] += $value['coins'];
                $this->data['amount'] += $value['amount'];

                $date = date('d-m-Y',strtotime($value['mis_datetime']));

                switch ($bucket) {
                    case 'hourly':
                        $this->getDataObject($bucket,$value['start_end_hour'],$value);
                        break;
                    case 'date_wise':
                        $this->getDataObject('date_wise',$date,$value);
                        break;
                    default:
                        foreach ($value[$bucket] as $value_bucket) {
                            $this->getDataObject($bucket,$value_bucket['type'],$value_bucket);
                        }
                        break;
                }
                
            }
        }

        return $this->data;
    }

    public function getDataObject($for,$key,$value){

        if(!empty($this->data[$for]) && !empty($this->data[$for][$key])){

            $this->data[$for][$key]['count'] += $value['count'];
            $this->data[$for][$key]['coins'] += $value['coins'];
            $this->data[$for][$key]['amount'] += $value['amount'];

        }else{

            $this->data[$for][$key]['count'] = $value['count'];
            $this->data[$for][$key]['coins'] = $value['coins'];
            $this->data[$for][$key]['amount'] = $value['amount'];
            $this->data[$for][$key][$for] = $key;
        }

    }

    public function getHourlyObject(){

        $array = ['12am-01am','01am-02am','02am-03am','03am-04am','04am-05am','05am-06am','06am-07am','07am-08am','08am-09am','09am-10am','10am-11am','11am-12pm','12pm-01pm','01pm-02pm','02pm-03pm','03pm-04pm','04pm-05pm','05pm-06pm','06pm-07pm','07pm-08pm','08pm-09pm','09pm-10pm','10pm-11pm','11pm-12am'];

        $response = [];

        foreach ($array as $value) {

            $response[$value] = [
                'count'=>0,
                'coins'=>0,
                'amount'=>0,
                'hourly'=>$value
            ];
        }

        return $response;
    }

    public function getDateWiseObject($request){

        $array = getDateRange($request);

        $response = [];

        foreach ($array as $value) {

            $response[$value] = [
                'count'=>0,
                'coins'=>0,
                'amount'=>0,
                'date_wise'=>$value
            ];
        }

        return $response;
    }

    public function spendingContent($request){

        $error_messages = $results = [];

        $date_alias = !empty($request['date_alias']) ? $request['date_alias'] : 'today';
        $type = !empty($request['type']) ? [$request['type']] : ['video','photo'];
        $limit = !empty($request['limit']) ? $request['limit'] : 10;
        $sort = !empty($request['sort']) && $request['sort'] == 'low' ? 1 : -1;
        $artist_ids = !empty($request['artist_id']) ? [$request['artist_id']] : [];

        $date = getDateByAlias($date_alias);

        $match_data = [];

        $match_data['created_at']['$gte'] = mongodb_converted_date($date['start_date']);
        $match_data['created_at']['$lte'] = mongodb_converted_date($date['end_date']);

        if(!empty($artist_ids)){
            $match_data['artist_id']['$in'] = $artist_ids;
        }

        if(in_array($request['type'],['gift','sticker'])){
            $match_data['entity'] = 'gifts';
            $match_data['type']['$exists'] = false;
            if(in_array($request['type'],['sticker'])){
                $match_data['type'] = 'stickers';
            }
        }else{
            $match_data['entity'] = 'contents';
        }

        $passbook = Passbook::raw(function($collection) use($match_data,$sort,$limit){

            $aggregate = [];

            $match = [
               '$match' => [
                    'txn_type'=>'paid',
                    'status'=>'success',
                    'artist_id'=>[
                        '$in'=>array_keys($this->artists)
                    ]
               ]  
            ];

            if(!empty($match_data)){
                $match['$match'] = array_merge($match['$match'],$match_data);
            }

            $group = [
                '$group' => [
                    '_id' => [
                       'content_id'=>'$entity_id',
                       'artist_id'=>'$artist_id',
                    ],
                    'count' => [    
                        '$sum' => 1
                    ],
                    'total_coins' => [
                        '$sum' => '$coins'
                    ]
                ]
            ];

            $project = [
                '$project' => [
                    '_id'=>'$_id.content_id',
                    'count'=>'$count',
                    'total_coins'=>'$total_coins',
                    'artist_id'=>'$_id.artist_id',
                ]
            ];

            $sort = [
                '$sort' => [
                    'count'=> $sort
                ]
            ];

            $limit = ['$limit' => 100];

            $aggregate[] = $match;
            $aggregate[] = $group;
            $aggregate[] = $project;
            $aggregate[] = $sort; 
            $aggregate[] = $limit;

            return $collection->aggregate($aggregate);

        });

        $passbook = $passbook->toArray();

        $content_ids = array_pluck($passbook,'_id');

        if(in_array($request['type'],['gift','sticker'])){
            $contents = \App\Models\Gift::whereIn('_id',$content_ids)
            ->get(['type','name','created_at','updated_at','published_at','coins','photo'])
            ->toArray();
        }else{
            $contents = \App\Models\Content::whereIn('_id',$content_ids)
            ->whereIn('type',$type)
            ->get(['type','name','created_at','updated_at','published_at','coins'])
            ->toArray();
        }

        foreach ($passbook as $key => &$value) {

            $data = [];

            $content_id = $value['_id'];

            $content = [];

            $content = head(array_where($contents, function ($key, $value) use ($content_id) {
                    if ($value['_id'] == $content_id) {
                        return $value;
                    }
            }));

            if(empty($content)){
                unset($passbook[$key]);
                continue;
            }

            $value = array_merge($value,$content);

            $value['artist_name'] = $this->artists[$value['artist_id']];

        }

        $passbook = array_slice($passbook,0,$limit);

        return [
            'error_messages' => $error_messages,
            'results' => $passbook
        ];
        
    }

    public function customerTxn($request){

        $error_messages = $results = [];

        $date_alias = !empty($request['date_alias']) ? $request['date_alias'] : 'today';
        $type = !empty($request['type']) ? [$request['type']] : ['video','photo'];
        $limit = !empty($request['limit']) ? $request['limit'] : 100;
        $sort = !empty($request['sort']) && $request['sort'] == 'low' ? 1 : -1 ;
        $artist_ids = !empty($request['artist_id']) ? [$request['artist_id']] : [];

        $date = getDateByAlias($date_alias);

        $match_data = [];

        $match_data['created_at']['$gte'] = mongodb_converted_date($date['start_date']);
        $match_data['created_at']['$lte'] = mongodb_converted_date($date['end_date']);

        if(!empty($artist_ids)){
            $match_data['artist_id']['$in'] = $artist_ids;
        }

        $match_data['txn_type'] = $request['txn_type'] == 'purchase' ? 'added' : 'paid';

        $passbook = Passbook::raw(function($collection) use($match_data,$limit,$sort){

            $aggregate = [];

            $match = [
               '$match' => [
                    'status'=>'success',
                    'artist_id'=>[
                        '$in'=>array_keys($this->artists)
                    ]
               ]  
            ];

            if(!empty($match_data)){
                $match['$match'] = array_merge($match['$match'],$match_data);
            }

            $group = [
                '$group' => [
                    '_id' => [
                       'customer_id'=>'$customer_id',
                       'artist_id'=>'$artist_id'
                    ],
                    'count' => [    
                        '$sum' => 1
                    ],
                    'total_coins' => [
                        '$sum' => '$coins'
                    ]
                ]
            ];

            $project = [
                '$project' => [
                    '_id'=>'$_id.customer_id',
                    'count'=>'$count',
                    'total_coins'=>'$total_coins',
                    'artist_id'=>'$_id.artist_id',
                ]
            ];

            $sort = [
                '$sort' => [
                    'total_coins'=> $sort
                ]
            ];

            $limit = ['$limit' => $limit];

            $aggregate[] = $match;
            $aggregate[] = $group;
            $aggregate[] = $project; 
            $aggregate[] = $sort; 
            $aggregate[] = $limit; 

            return $collection->aggregate($aggregate);

        });

        $passbook = $passbook->toArray();

        $passbook = $this->syncCustomerData($passbook,$limit,$sort);

        return [
            'error_messages' => $error_messages,
            'results' => $passbook
        ];
        
    }

    public function syncCustomerData($passbook,$limit,$sort){

        $customer_ids = array_pluck($passbook,'_id');

        $customers = \App\Models\Customer::whereIn('_id',$customer_ids)
            ->get(['last_name','first_name','created_at','coins','email','picture'])
            ->toArray();

        $passbook_new = [];
        $count = 0;

        foreach ($passbook as &$value) {

            $data = [];

            $customer_id = $value['_id'];

            $customer = [];

            $customer = head(array_where($customers, function ($key, $value) use ($customer_id) {
                    if ($value['_id'] == $customer_id) {
                        return $value;
                    }
            }));

            if(empty($customer)){
                continue;
            }

            $value = array_merge($value,$customer);

            $value['artist_name'] = $this->artists[$value['artist_id']];

            $first_name = !empty($value['first_name']) ? $value['first_name'] : "";
            $last_name = !empty($value['last_name']) ? $value['last_name'] : "";

            $value['customer_name'] = ucwords(strtolower($first_name." ".$last_name));

            $count++;
        }

        return $passbook;
    }

    



}
