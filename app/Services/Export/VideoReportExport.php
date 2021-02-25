<?php 



namespace App\Services\Export;
use config;
use Input;
use Excel;
use Carbon;

class VideoReportExport
{
    /**
    * @return \Illuminate\Support\Collection
     */


    public function VideoPurchaseRport($videodata)
    {

      Excel::create('VideoContentReport', function($excel)use ($videodata) {
      $excel->sheet('VideoCOntentReport', function ($sheet) use ($videodata) 
      {

	      $excelData = [];
	      foreach ($videodata as $key => $value) {
		      $revenue = 0;
		      if($value['coins_spends'] > 0)
		      {
			      $revenue = $value['coins_spends'] * 1.5 ;
		      }
		$dailystatsexcel = [
                            'id' => isset($value['_id']) ? $value['_id'] : '-',
                            'name' => isset($value['name']) ? $value['name'] : '-',
			    'coins' => isset($value['coins']) ? $value['coins'] : '-',
   		            'purchased_count' => isset($value['purchased_count']) ? $value['purchased_count'] : '-',
                            'coins_spends' => isset($value['coins_spends']) ? $value['coins_spends'] : '-',
                            'coins spends revenue * 1.5' => $revenue,

		    ];
                array_push($excelData, $dailystatsexcel);	
	     }
	     $sheet->fromArray($excelData);

	  });

      })->download('csv');
     return back();
    }
}



