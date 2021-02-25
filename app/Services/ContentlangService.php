<?php

namespace App\Services;

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

use App\Repositories\Contracts\ContentlangInterface;
use App\Models\Contentlang as Contentlang;

class ContentlangService
{
	protected $repObj;
    protected $contentlang;

    public function __construct(Contentlang $contentlang, ContentlangInterface $repObj)
    {
        $this->contentlang   = $contentlang;
        $this->repObj       = $repObj;
    }

    public function update($request, $id)
    { 
        $data               =   $request->all();
        $error_messages     =   $results = [];
        $slug               =   str_slug($data['name']);

        array_set($data, 'slug', $slug);
        $category_count = $this->repObj->checkUniqueOnUpdate($id, 'slug', $slug);
        if ($category_count > 0) {
            $error_messages[] = 'Content language already exists : ' . str_replace("-", " ", ucwords($slug));
        }

        if(empty($error_messages)){
            $results['contentlang']   = $this->repObj->update($data, $id);
        }
        return ['error_messages' => $error_messages, 'results' => $results];
    }
}