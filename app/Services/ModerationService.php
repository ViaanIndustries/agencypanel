<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 15/10/18
 * Time: 3:50 PM
 */

namespace App\Services;

use Carbon\Carbon;
use Input;
use Config;
use League\Flysystem\Exception;
use Session;
use Request;
use Log;
use Storage;
use Cache;

class ModerationService
{
    public function moderationContent($requestData)
    {
        $error_messages = $results = [];

        $artist_id = !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $blocked_by = !empty($requestData['blocked_by']) ? $requestData['blocked_by'] : '';
        $content_id = !empty($requestData['entity_id']) ? $requestData['entity_id'] : '';
        $entity = !empty($requestData['entity']) ? $requestData['entity'] : '';
        $status = !empty($requestData['status']) ? $requestData['status'] : '';
        $created_at = !empty($requestData['created_at']) ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = !empty($requestData['created_at_end']) ? hyphen_date($requestData['created_at_end']) : '';

        $perpage = 10;

        $appends_array = array(
            'artist_id' => $artist_id,
            'blocked_by' => $blocked_by,
            'content_id' => $content_id,
            'status' => $status,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end
        );

        $query = \App\Models\Moderation::
        with(array('artist' => function ($query) {
            $query->select('_id', 'first_name', 'last_name');
        }))->with(array('customer' => function ($query) {
            $query->select('_id', 'first_name', 'last_name', 'email');
        }))->with(array('content' => function ($query) {
            $query->select('_id', 'name', 'photo');
        }))->with(array('comment' => function ($query) {
            $query->select('_id');
        }))->where('entity', 'content');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($blocked_by != '') {
            $query->where('blocked_by', $blocked_by);
        }

        if ($content_id != '') {
            $query->where('entity_id', $content_id);
        }

        if ($entity != '') {
            $query->where('entity', $entity);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($created_at != '') {
            $query->where('created_at', '>=', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<=', mongodb_end_date($created_at_end));
        }

        $results['contents'] = $query->paginate($perpage)->toArray();

        $results['appends_array'] = $appends_array;


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function moderationComment()
    {
        $error_messages = $results = [];

        $artist_id = !empty($requestData['artist_id']) ? $requestData['artist_id'] : '';
        $blocked_by = !empty($requestData['blocked_by']) ? $requestData['blocked_by'] : '';
        $content_id = !empty($requestData['content_id']) ? $requestData['content_id'] : '';
        $entity = !empty($requestData['entity']) ? $requestData['entity'] : '';
        $status = !empty($requestData['status']) ? $requestData['status'] : '';
        $created_at = !empty($requestData['created_at']) ? hyphen_date($requestData['created_at']) : '';
        $created_at_end = !empty($requestData['created_at_end']) ? hyphen_date($requestData['created_at_end']) : '';

        $perpage = 10;

        $appends_array = array(
            'artist_id' => $artist_id,
            'blocked_by' => $blocked_by,
            'content_id' => $content_id,
            'status' => $status,
            'created_at' => $created_at,
            'created_at_end' => $created_at_end
        );

        $query = \App\Models\Moderation::with(['customer', 'artist', 'content', 'comment'])
            ->where('entity', 'comment');

        if ($artist_id != '') {
            $query->where('artist_id', $artist_id);
        }

        if ($blocked_by != '') {
            $query->where('blocked_by', $blocked_by);
        }

        if ($content_id != '') {
            $query->where('entity_id', $content_id);
        }

        if ($entity != '') {
            $query->where('entity', $entity);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        if ($created_at != '') {
            $query->where('created_at', '>=', mongodb_start_date($created_at));
        }

        if ($created_at_end != '') {
            $query->where('created_at', '<=', mongodb_end_date($created_at_end));
        }

        $results['contents'] = $query->paginate($perpage);

        $results['appends_array'] = $appends_array;


        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function moderationUser()
    {
        $error_messages = $results = [];

        $perpage = 10;

        $responseData = \App\Models\Moderation::where('entity', 'customer')->paginate($perpage);

        return ['error_messages' => $error_messages, 'results' => $responseData];
    }
}