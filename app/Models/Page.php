<?php

namespace App\Models;

use App\Models\Basemodel;

/**
 * ModelName : Page.
 * Maintains a list of functions used for Page.
 *
 * @author Pranita S. <pranita@razrmedia.com>
 */

class Page extends Basemodel
{
    private $section_types = array(
        'artist'    => 'Artist',
        'banner'    => 'Banner',
        'content'   => 'Content',
    );

    protected $connection = 'arms_contents';

    protected $primaryKey = '_id';

    protected $collection = "pages";

    public function Artist(){
        return $this->belongsTo('App\Models\Cmsuser','artist_id');
    }

    public function artists(){
        return $this->belongsToMany('App\Models\Cmsuser',null, 'pages', 'artists');
    }

    public function banners(){
        return $this->belongsToMany('App\Models\Banner',null, 'pages', 'banners');
    }

    public function contents(){
        return $this->belongsToMany('App\Models\Content',null, 'pages', 'contents');
    }

    public function getSetionTypes() {
        return $this->section_types;
    }

    public function getBucketContents($bucket_id) {
        $data       = [];
        $level      = 1;

        $query = \App\Models\Content::with('bucket')
            ->where('bucket_id', $bucket_id)
            ->where('level', intval($level))
            ->orderBy('published_at', 'desc');

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
                    $data['results'][$content_id]=array('content_id'=>$content_id, 'content_name'=> $content_name, 'content_name_short' => substr($content_name, 0, 30));
                }
            }
        }

        return $data;
    }

    /**
     * Get the bucket that belongs to page section.
     */
    public function bucket(){
        return $this->belongsTo('App\Models\Bucket','bucket_id');
    }
}
