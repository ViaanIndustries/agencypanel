<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\TemplateInterface;
use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Template as Communicate;
use Config;
class TemplateRepository extends AbstractRepository implements TemplateInterface
{
    protected $modelClassName = 'App\Models\Template';

    public function index($requestData)
    {

        $results            =     [];
        $perpage            =     ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $label               =     (isset($requestData['label']) && $requestData['label'] != '')  ? $requestData['label'] : '';
        $status             =     (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';

        $appends_array      =     array('label' => $label, 'status' => $status);

        $query              =     \App\Models\Template::orderBy('label');

       if($label != ''){
           $query->where('label', 'LIKE', '%'. $label .'%');
       }

       if($status != ''){
           $query->where('status', $status);
       }


        $results['templates']       		=     $query->paginate($perpage);
        $results['appends_array']     	=     $appends_array;

        //print_pretty($results);exit;
  		//print_pretty($query->paginate($perpage)->toArray());exit;
        return $results;
    }
}
