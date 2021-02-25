<?php

namespace App\Services;

/**
 * ServiceName : Entity
 * Maintains a list of functions used for Entity.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-05-27
 * @link        http://bollyfame.com/
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license/
 */

use Illuminate\Http\Request;
use Input;
use Redirect;
use Config;
use Session;

class EntityService
{
    public function __construct()
    {

    }


    /**
     * Default Method
     *
     * @param   array   $request Service Method Request Data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-27
     */
    public function default($request)
    {
        $results        = array('something' => 'something_val');

        return $results;
    }


    /**
     * Save Entity Like data in
     *
     * @param   array   $data Entity Like Data
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-05-27
     */
    public function saveLike($data)
    {
        $return         = null;
        $entity         = (isset($data['entity'])) ? trim($data['entity']) : 'content';
        $entity_id      = (isset($data['entity_id'])) ? trim($data['entity_id']) : '';
        $artist_id      = (isset($data['artist_id'])) ? trim($data['artist_id']) : '';
        $customer_id    = (isset($data['customer_id'])) ? trim($data['customer_id']) : '';
        $type           = (isset($data['type'])) ? trim($data['type']) : 'like';
        $new_entry      = false;

        $contestant_model   = null;

        if(!$customer_id) {
            $customer_id = $this->jwtauth->customerFromToken()['_id'];
        }

        if($entity) {
            // Find Entity in database
            $entity_model = null;
            switch (strtolower($entity)) {
                case 'contestant':
                    $entity_model = \App\Models\Cmsuser::where('_id', '=', $entity_id)->first();
                    # code...
                    break;

                case 'content':
                default:
                    $entity_model = \App\Models\Content::where('_id', '=', $entity_id)->first();
                    if($entity_model) {
                        $contestant_model = \App\Models\Cmsuser::where('_id', '=', $entity_model->artist_id)->first();
                    }
                    break;
            }

            if($entity_model) {
                //insert update customer like
                $like_data = [
                    'entity'        => $entity,
                    'entity_id'     => $entity_id,
                    'artist_id'     => $artist_id,
                    'customer_id'   => $customer_id,
                    'type'          => $type,
                    'status'        => 'active',
                ];

                if (!empty($data['created_at']) && $data['created_at'] != '') {
                    array_set($like_data, 'created_at', $data['created_at']);
                }

                $like_model = \App\Models\Like::where('customer_id', '=', $customer_id)->where('entity', $entity)->where('entity_id', $entity_id)->where('artist_id', $artist_id)->first();

                if ($like_model) {
                    // For time being dont update like option once entry have been done
                    //$like_model->update($like_data);
                }
                else {
                    $new_entry  = true;
                    $like_model = new \App\Models\Like($like_data);
                    $like_model->save();
                }

                if($like_model && $new_entry) {
                    // Update Entity Likes data
                    $likes = [];
                    if(isset($entity_model->likes)) {
                        $likes = $entity_model->likes;
                    }
                    else {
                        $likes = array(
                            'internal' => 0,
                        );
                    }

                    if($likes) {
                        switch (strtolower($type)) {
                            case 'hot':
                                if(isset($likes['hot'])) {
                                    $likes['hot'] = $likes['hot'] + 1;
                                }
                                else {
                                    $likes['hot'] = 1;
                                }
                                break;

                            case 'cold':
                                if(isset($likes['cold'])) {
                                    $likes['cold'] = $likes['cold'] + 1;
                                }
                                else {
                                    $likes['cold'] = 1;
                                }
                                break;

                            case 'like':
                            case 'normal':
                            default:
                                if(isset($likes['internal'])) {
                                    $likes['internal'] = $likes['internal'] + 1;
                                }
                                else {
                                    $likes['internal'] = 1;
                                }
                                break;
                        }
                    }

                    $entity_model->likes = $likes;


                    // Update Entity Stats as per like data
                    // Stats -> likes = social + internal

                    $stats = [];
                    if(isset($entity_model->stats)) {
                        $stats = $entity_model->stats;
                    }
                    else {
                        $stats = array(
                            'likes'     => 0,
                            'comments'  => 0,
                            'shares'    => 0,
                            'childrens' => 0,
                            'hot_likes' => 0,
                            'cold_likes'=> 0,

                        );
                    }

                    if($stats) {
                        switch (strtolower($type)) {
                            case 'hot':
                                if(isset($stats['hot_likes'])) {
                                    $stats['hot_likes'] = $stats['hot_likes'] + 1;
                                }
                                else {
                                    $stats['hot_likes'] = 1;
                                }
                                break;

                            case 'cold':
                                if(isset($stats['cold_likes'])) {
                                    $stats['cold_likes'] = $stats['cold_likes'] + 1;
                                }
                                else {
                                    $stats['cold_likes'] = 1;
                                }
                                break;

                            case 'like':
                            case 'normal':
                            default:
                                if(isset($stats['likes'])) {
                                    $stats['likes'] = $stats['likes'] + 1;
                                }
                                else {
                                    $stats['likes'] = 1;
                                }
                                break;
                        }
                    }

                    $entity_model->stats = $stats;

                    $entity_model->save();


                    // Update Contents Paid Content Likes Stats
                    if($contestant_model) {
                        $contestant_stats = [];
                        if(isset($contestant_model->stats)) {
                            $contestant_stats = $contestant_model->stats;
                        }
                        else {
                            $contestant_stats = array(
                                'likes'     => 0,
                                'comments'  => 0,
                                'shares'    => 0,
                                'childrens' => 0,
                                'hot_likes' => 0,
                                'cold_likes'=> 0,
                            );
                        }

                        if($contestant_stats) {
                            switch (strtolower($type)) {
                                case 'hot':
                                    if(isset($contestant_stats['hot_likes'])) {
                                        $contestant_stats['hot_likes'] = $contestant_stats['hot_likes'] + 1;
                                    }
                                    else {
                                        $contestant_stats['hot_likes'] = 1;
                                    }
                                    break;

                                case 'cold':
                                    if(isset($contestant_stats['cold_likes'])) {
                                        $contestant_stats['cold_likes'] = $contestant_stats['cold_likes'] + 1;
                                    }
                                    else {
                                        $contestant_stats['cold_likes'] = 1;
                                    }
                                    break;

                                case 'like':
                                case 'normal':
                                default:
                                    if(isset($contestant_stats['likes'])) {
                                        $contestant_stats['likes'] = $contestant_stats['likes'] + 1;
                                    }
                                    else {
                                        $contestant_stats['likes'] = 1;
                                    }
                                    break;
                            }


                            $contestant_model->stats = $contestant_stats;

                            $contestant_model->save();
                        }
                    }
                }

                $return  = $like_model;
            }
            else {
                // Entity Not Found
            }
        }

        return $return;
    }

}
