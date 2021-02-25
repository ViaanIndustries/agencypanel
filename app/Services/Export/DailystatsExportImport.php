<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 30/7/18
 * Time: 5:53 PM
 */

namespace App\Services\Export;

use App\Services\ArtistService;

use config;
use Input;
use Excel;
use Carbon;

class DailystatsExportImport
{
    protected $artistservice;

    public function __construct(ArtistService $artistservice)
    {
        $this->artistservice = $artistservice;
    }

    public function export_dailystats($data)
    {
        $dailystatsinfo = $this->artistservice->getDailystats($data);

        $dailystats = $dailystatsinfo['results']['dailystats'];


        if (sizeof($dailystats) > 0) {

            \Excel::create('Dailystats', function ($excel) use ($dailystats) {

                $excel->sheet('Dailystats', function ($sheet) use ($dailystats) {

                    $excelData = [];
                    foreach ($dailystats as $key => $value) {

                        $dailystatsexcel = [
                            'id' => isset($value['_id']) ? $value['_id'] : '-',
                            'stats_at' => isset($value['stats_at']) ? $value['stats_at'] : '-',
                            'artist_id' => isset($value['artist_id']) ? $value['artist_id'] : '-',
                            'revenue' => isset($value['revenue']) ? $value['revenue'] : '-',
                            'comments' => isset($value['comments']) ? $value['comments'] : '-',
                            'likes' => isset($value['likes']) ? $value['likes'] : '-',
                            'paid_customers' => isset($value['paid_customers']) ? ($value['paid_customers']) : '-',
                            'time_spent' => isset($value['time_spent']) ? $value['time_spent'] : '-',
                            'downloads' => isset($value['downloads']) ? $value['downloads'] : '-',
                            'active_users' => isset($value['active_users']) ? $value['active_users'] : '-',
                            'status' => isset($value['status']) ? $value['status'] : '-',
                        ];

                        array_push($excelData, $dailystatsexcel);
                    }

                    $sheet->fromArray($excelData);
                });

            })->download('xlsx');
        }
        return true;
    }

    public function import_dailystats()
    {

        if (Input::hasFile('dailystats_import')) {
            $path = Input::file('dailystats_import')->getRealPath();
            $data = Excel::load($path, function ($reader) {

            })->get();

            if (!empty($data) && $data->count()) {
                foreach ($data as $key => $value) {

                    if (!empty($value['artist_id'])) {

                        if (empty($value['id'])) {

                            $start_date = (isset($value['stats_at']) && $value['stats_at'] != '') ? hyphen_date($value['stats_at']) : '';
                            $start_date = new \MongoDB\BSON\UTCDateTime(strtotime($start_date) * 1000);


                            $insert = [
                                'stats_at' => !empty($start_date) ? $start_date : Carbon::now(),
                                'artist_id' => $value->artist_id,
                                'revenue' => intval($value->revenue),
                                'comments' => intval($value->comments),
                                'likes' => intval($value->likes),
                                'paid_customers' => intval($value->paid_customers),
                                'time_spent' => $value->time_spent,
                                'downloads' => intval($value->downloads),
                                'active_users' => intval($value->active_users),
                                'status' => $value->status,
                            ];

                            $dailystats = new \App\Models\Dailystats($insert);
                            $dailystats->save();

                        } else {

                            $start_date = (isset($value['stats_at']) && $value['stats_at'] != '') ? hyphen_date($value['stats_at']) : '';
                            $start_date = new \MongoDB\BSON\UTCDateTime(strtotime($start_date) * 1000);

                            $update = [
                                'stats_at' => !empty($start_date) ? $start_date : Carbon::now(),
                                'artist_id' => $value->artist_id,
                                'revenue' => intval($value->revenue),
                                'comments' => intval($value->comments),
                                'likes' => intval($value->likes),
                                'paid_customers' => intval($value->paid_customers),
                                'time_spent' => $value->time_spent,
                                'downloads' => intval($value->downloads),
                                'active_users' => intval($value->active_users),
                                'status' => $value->status,

                            ];

                            $dailystats = \App\Models\Dailystats::findOrFail($value['id']);
                            $dailystats->update($update);

                        }
                    }
                }

            }
        }
        return back();
    }
}