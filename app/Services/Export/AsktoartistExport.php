<?php
/**
 * Created by PhpStorm.
 * User: sibani
 * Date: 28/5/18
 * Time: 2:36 PM
 */

namespace App\Services\Export;

use App\Services\ArtistService;

use config;

class AsktoartistExport
{
    protected $artistservice;

    public function __construct(ArtistService $artistservice)
    {
        $this->artistservice = $artistservice;
    }

    public function export_asktoartist($data)
    {
        $ask_to_artist = $this->artistservice->askToArtist($data);
        $ask_to_artist = $ask_to_artist['questions'];

        if (sizeof($ask_to_artist) > 0) {
            \Excel::create('Asktoartist', function ($excel) use ($ask_to_artist) {
                $excel->sheet('asktoartist', function ($sheet) use ($ask_to_artist) {
                    $excelData = [];
                    foreach ($ask_to_artist as $key => $value) {
                        $artist_name = isset($value['artist']) && isset($value['artist']['first_name']) ? $value['artist']['first_name'] . ' ' . $value['artist']['last_name'] : '-';
                        $customer_name = isset($value['customer']) && isset($value['customer']['first_name']) ? ($value['customer']['first_name']) : '-';
                        $customer_email = isset($value['customer']) && isset($value['customer']['email']) ? ($value['customer']['email']) : '-';
                        $asktoartistexcel = [
                            'artist_name' => $artist_name,
                            'customer_name' => $customer_name,
                            'customer_email' => $customer_email,
                            'question' => $value['question'],
                            'created_date' => $value['created_at'],
                        ];
                        array_push($excelData, $asktoartistexcel);
                    }
                    $sheet->fromArray($excelData);
                });
            })->download('xlsx');
        }
        return true;
    }
}