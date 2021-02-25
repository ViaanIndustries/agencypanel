<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 12/6/18
 * Time: 3:22 PM
 */

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\AuctionproductInterface;
use App\Repositories\Contracts\FanInterface;
use App\Models\Auctionproduct as Auctionproduct;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;

class AuctionproductRepository extends AbstractRepository implements AuctionproductInterface
{
    protected $modelClassName = 'App\Models\Auctionproduct';


    public function index($requestData, $perpage = NULL)
    {

        $results = [];
        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'active';

        $appends_array = array('artist_id' => $artist_id, 'name' => $name, 'status' => $status);

        $query = \App\Models\Auctionproduct::orderBy('created_at', 'desc')
            ->select('_id','artist_id','name','description','minchips','status','slug');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        if ($name != '') {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($status != '') {
            $query->where('status', $status);
        }


        $results['auctionproducts'] = $query->paginate($perpage);

        
        $results['appends_array'] = $appends_array;
        return $results;
    }

    public function store($requestData)
    {
        $auctionproduct = new $this->model($requestData);
        $auctionproduct->save();

        return $auctionproduct;
    }

    public function update($data, $id)
    {
        $auctionproduct = $this->model->findOrFail($id);

        $auctionproduct->update($data);
        return $auctionproduct;
    }
}