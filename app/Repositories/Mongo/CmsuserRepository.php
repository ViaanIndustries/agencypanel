<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\CmsuserInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use Config;
use App\Services\Jwtauth;
use Carbon;

class CmsuserRepository extends AbstractRepository implements CmsuserInterface
{

    protected $modelClassName = 'App\Models\Cmsuser';


    protected $jwtauth;


    public function __construct(Jwtauth $jwtauth)
    {
        parent::__construct();
        $this->jwtauth = $jwtauth;
    }


    public function getOrderQuery($requestData)
    {
        $order_status = (isset($requestData['order_status']) && $requestData['order_status'] != '') ? $requestData['order_status'] : 'successful';
        $artist_id = (isset($requestData['artist_id']) && $requestData['artist_id'] != '') ? $requestData['artist_id'] : '';
        $package_id = (isset($requestData['package_id']) && $requestData['package_id'] != '') ? $requestData['package_id'] : '';
        $platform = (isset($requestData['platform']) && $requestData['platform'] != '') ? $requestData['platform'] : '';
        $created_at = (isset($requestData['created_at']) && $requestData['created_at'] != '') ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = (isset($requestData['created_at_end']) && $requestData['created_at_end'] != '') ? hyphen_date($requestData['created_at_end']) : '';
        $vendor_order_id = (isset($requestData['vendor_order_id']) && $requestData['vendor_order_id'] != '') ? $requestData['vendor_order_id'] : '';

        $query = \App\Models\Order::with('artist', 'package', 'customer')->orderBy('created_at', 'desc');

        if ($order_status != '') {
            $query->where('order_status', $order_status);
        }

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }
        if ($package_id != '') {

            $query->where('package_id', $package_id);
        }
        if ($vendor_order_id != '') {
            $query->where('vendor_order_id', $vendor_order_id);
        }

        if ($created_at != '') {
            $query->where('created_at', '>=', new \DateTime(date("d-m-Y", strtotime($created_at))));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<=', new \DateTime(date("d-m-Y", strtotime($created_at_end))));
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }


        return $query;

    }


    public function index($requestData)
    {

        $results = [];
        $perpage = ($requestData['perpage'] == NULL) ? Config::get('app.perpage') : intval($requestData['perpage']);
        $name = (isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : '';
        $email = (isset($requestData['email']) && $requestData['email'] != '') ? $requestData['email'] : '';
        $status = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : '';
        $roles = (isset($requestData['roles']) && $requestData['roles'] != '') ? $requestData['roles'] : '';

        $appends_array = array('name' => $name, 'status' => $status, 'email' => $email, 'roles' => $roles);

        $query = \App\Models\Cmsuser::with('roles')->orderBy('first_name', 'last_name');
//        $query = \App\Models\Cmsuser::orderBy('first_name', 'last_name');

        if ($name != '') {
            $query->where('first_name', 'LIKE', '%' . $name . '%');
            $query->orWhere('last_name', 'LIKE', '%' . $name . '%');
        }

        if ($email != '') {
            $query->where('email', 'LIKE', '%' . $email . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }
        if ($roles != '') {
            $query->where('roles', $roles);
        }


        $results['cmsusers'] = $query->paginate($perpage);
        $results['appends_array'] = $appends_array;

        return $results;
    }

    public function paginateForApi($perpage = NULL)
    {

        $perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $data = $this->model->with('roles')->orderBy('_id')->paginate($perpage)->toArray();

        $responeData = [];
        $responeData['list'] = (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total'] = (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page'] = (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page'] = (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page'] = (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from'] = (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to'] = (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }


    public function paginate($perpage = NULL)
    {
        $perpage = ($perpage == NULL) ? \Config::get('app.perpage') : intval($perpage);
        return $this->model->with('roles')->orderBy('id')->paginate($perpage);
    }


    public function store($postData)
    {
        $error_messages = array();
        $data = array_except($postData, ['password_confirmation']);

        //used keep the relastionship cloumn atleast if not selected
        array_set($data, 'roles', ["59857f03af21a2d02523fbe2"]);
 	    if (isset($postData['photo']) && isset($postData['photo']['thumb'])) {
            array_set($data, 'picture', $postData['photo']['thumb']);
        }

        $user = new $this->model($data);
        $user->save();
        $this->syncRoles($postData, $user);
        $this->artistConfig($postData, $user);
        return $user;
    }


    public function update($postData, $id)
    {
        $error_messages = array();
        $data = array_except($postData, ['_method', '_token', 'password_confirmation', 'email', 'password', 'picture']);
        $user = $this->model->findOrFail(trim($id));

        if (isset($postData['photo']) && isset($postData['photo']['thumb'])) {
            array_set($data, 'picture', $postData['photo']['thumb']);
        }

        $user->update($data);
        $this->syncRoles($postData, $user);
        $this->artistConfig($postData, $user);
        return $user;
    }


    //manage syncRoles
    public function syncRoles($postData, $user)
    {
        \App\Models\Role::whereIn('cmsusers', [$user->_id])->pull('cmsusers', $user->_id);

        if (!empty($postData['roles'])) {
            $roles = array_map('trim', $postData['roles']);
            $user->roles()->sync(array());
            foreach ($roles as $key => $value) {
                $user->roles()->attach($value);
            }
        }
    }


    public function profile()
    {

        $user_id = $this->jwtauth->customerIdFromToken();
        $user = \App\Models\Cmsuser::where('_id', '=', $user_id)->first();

        return $user;
    }


    public function isLive($postData)
    {

        $error_messages = array();
        $is_live = ($postData['is_live'] == 'false') ? false : true;
        $userer_id = $this->jwtauth->customerIdFromToken();
        $user = \App\Models\Cmsuser::where('_id', trim($userer_id))->first();
        $user->update(['is_live' => $is_live]);
        return $user;
    }


    public function getIsLive($postData)
    {
        $error_messages = array();
        $data = $postData;
        $artist_id = (isset($data['artist_id'])) ? trim($data['artist_id']) : '';
        $user = \App\Models\Cmsuser::find($artist_id, ['_id', 'first_name', 'last_name', 'is_live', 'last_visited']);
        $user['is_live'] = (isset($user['is_live'])) ? $user['is_live'] : false;
        return $user;
    }


    public function changePassword($postData, $id)
    {
        $error_messages = array();
        $data = array_except($postData, []);
        $user = \App\Models\Cmsuser::where('_id', trim($id))->first();

        $user->update($data);

        return $user;
    }

    public function resetPassword($postData, $id)
    {
        $error_messages = array();
        $data = array_except($postData, []);
        $user = \App\Models\Cmsuser::where('_id', trim($id))->first();
        $user->update($data);
        return $user;
    }

    /**
     * Create Artist Config for User if User has 'artist' role
     *
     * @param   postData
     * @param   user
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-04-13
     */
    public function artistConfig($postData, $user)
    {
        $artist_role_id = '59857f03af21a2d02523fbe2';

        if (!empty($postData['roles']) && in_array($artist_role_id, $postData['roles'])) {
            $data = [];
            $celebname                              =       preg_replace('/\s+/', '', strtolower(trim(@$user['first_name'])).strtolower(trim(@$user['last_name'])));

            $data['artist_id']                      =       $user->_id;
            $data['channel_namespace']              =       $celebname;
            $data['fmc_default_topic_id']           =       $celebname;
            $data['fmc_default_topic_id_test']      =       $celebname."test";
            $data['channel_namespace']              =       $celebname.'_'.$user->_id;
            $data['android_version_name']           =       "1.0.0";
            $data['android_version_no']             =       1;
            $data['ios_version_name']               =       "1.0.0";
            $data['ios_version_no']                 =       1;
            $data['last_updated_gifts']             =       Carbon::now();
            $data['last_updated_buckets']           =       Carbon::now();
            $data['last_updated_packages']          =       Carbon::now();
            $data['last_visited']                   =       Carbon::now();

            $artistConfig = \App\Models\Artistconfig::where('artist_id', '=', $data['artist_id'])->first();

            if(!$artistConfig) {
                $artistConfig = new \App\Models\Artistconfig($data);
                $save = $artistConfig->save();
            }
        }
    }
}
