<?php

namespace App\Repositories\Mongo;

use App\Repositories\Contracts\BucketlangInterface;
use App\Repositories\AbstractRepository as AbstractRepository;
use App\Models\Bucketlang as Bucketlanguage;
use Config, DB;


class BucketlangRepository extends AbstractRepository implements BucketlangInterface
{
	protected $modelClassName = 'App\Models\Bucketlang';
}