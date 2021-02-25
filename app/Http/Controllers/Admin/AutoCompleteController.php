<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;
use Storage;


use App\Http\Controllers\Controller;

use App\Models\Customer as Customer;
use App\Models\Cmsuser as Cmsuser;


class AutoCompleteController extends Controller
{
	 public function autoComplete(Request $request) {
       // echo json_encode(['dj'=>1000]);exit;
        $query = $request->get('term','');
        $msg='';
        $customers=Customer::where('first_name','LIKE','%'.$query.'%')->get();

        $data=array();
        foreach ($customers as $customer) {
                $data['results'][]=array('id'=>$customer->_id,'value'=>$customer->first_name);
                $msg.="<option value=".$customer->_id.">".$customer->first_name."</option>";
        }
        if(count($data))
             return json_encode($data);
        else{

       		$data['results'][]=['value'=>'No Result Found','id'=>''];
       		  return json_encode($data);
         }

    }

    public function artist(Request $request) {
        $keyword = $request->get('q','');
        $msg = '';
        $artist_role_ids = \App\Models\Role::where('slug', 'artist')->lists('_id');
        $artist_role_ids = ($artist_role_ids) ? $artist_role_ids->toArray() : [];
        $artists = Cmsuser::where('status', '=', 'active')->whereIn('roles', $artist_role_ids)->where('first_name','LIKE','%'.$keyword.'%')->get()->pluck('full_name', 'id');

        $data=array();
        if($artists) {
            foreach ($artists as $artist_id => $artist_name) {
                $data['results'][]=array('id'=>$artist_id, 'text'=>$artist_name);
                $msg.="<option value=" . $artist_id . ">" . $artist_name ."</option>";
            }
        }

        if(count($data)) {
            return json_encode($data);
        }
        else {
            $data['results'][]=['id'=>'', 'text'=>'No Result Found'];
            return json_encode($data);
        }
    }

    public function content($bucket_id, Request $request) {
        $keyword    = $request->get('q','');
        $contents   = [];
        $data       = [];
        $msg        = '';

        $level      = 1;

        $query = \App\Models\Content::with('bucket')
            ->where('bucket_id', $bucket_id)
            ->where('level', intval($level))
            ->where('status', '=', 'active')
            ->orderBy('published_at', 'desc');

        if ($keyword != '') {
            $query->where('name', 'LIKE', '%' . $keyword . '%');
        }

        $contents = $query->get();
        if($contents) {
            foreach ($contents as $content_key => $content) {
                $content_id     = $content->id;
                $content_name   = '';
                $content_name   = (isset($content->name) && $content->name) ? $content->name : '';

                if(!$content_name) {
                    $content_name = (isset($content->caption) && $content->caption) ? $content->caption : '';
                }

                if(!$content_name) {
                    $content_name = (isset($content->caption) && $content->caption) ? $content->caption : '';
                }

                if($content_name) {
                    $content_name = substr($content_name, 0, 25);
                    $data['results'][]=array('id'=>$content_id, 'text'=> $content_name);
                    $msg.="<option value=" . $content_id . ">" . $content_name ."</option>";
                }
            }
        }

        if(count($data)) {
            return json_encode($data);
        }
        else {
            $data['results'][]=['id'=>'', 'text'=>'No Result Found'];
            return json_encode($data);
        }
    }
}

