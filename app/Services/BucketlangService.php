<?php

namespace App\Services;

use Input, Config;
use App\Repositories\Contracts\BucketlangInterface;
use App\Models\Bucketlang as Bucketlanguage;

class BucketlangService {

	protected $bucketLang;
    protected $repObj;

    public function __construct(Bucketlanguage $bucketLang, BucketlangInterface $repObj) {
        $this->bucketLang = $bucketLang;
        $this->repObj = $repObj;
    }

    public function store($request)
    {
        $data               =   $request->all();
        $error_messages     =   $results = [];

        if(empty($error_messages)) {
            $results['bucketlang']    =   $this->repObj->store($data);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

}

