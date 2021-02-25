<?php
namespace App\Http\Traits;
use App\State;
use App\Sports;
use App\Level;
use App\Banner;
use App\City;
use App\Price;
use App\Balls;
trait MasterTrait {
   
   public function getAllStates()
   {
		$state = State::all();
		$list='';
		$list .='<option value="">Select State</option>';
		foreach($state as $s)
		{
		   $list .='<option value="'.$s['_id'].'">'.$s['state_name'].'</option>';
		}
		return $list;
   }
   
   public function getAllSports()
   {
		$sports = Sports::all();
		$list='';
		$list .='<option value="">Check Event List</option>';

		foreach($sports as $s)
		{
		   $list .='<option value="'.$s['sport_name'].'">'.$s['sport_name'].'</option>';
		   
		}
		return $list;
   }
   
   //public function getAllLevels()
   //{
	   	//$level = Level::all();
		//$list='';
		//foreach($level as $s)
		//{
		   //$list .='<option value="'.$s['_id'].'">'.$s['level_name'].'</option>';
		   
		//}
		//return $list;   
   //}
   public function getAllBanners()
   {
	   	$banner = Banner::all();
		$list='';
		$list .='<option value="">No Banner</option>';
		foreach($banner as $s)
		{
		   $list .='<option value="'.$s['_id'].'">'.$s['title'].'</option>';
		   
		}
		return $list;   
   }
   public function getCitiesFromState($stateid)
   {
	    $city = City::where('state_id', $stateid)->get();
		$list='';
 		foreach($city as $s)
		{
		   $list .='<option value="'.$s['_id'].'">'.$s['city_name'].'</option>';
		   
		}
		return $list;   
   }
   


	 public function getAllPrices()
   {
                $price = Price::all();
                $list='';
                $list .='<option value="">Select Prices</option>';
                foreach($price as $s)
                {
                   $list .='<option value="'.$s['name'].'">'.$s['name'].'</option>';
                }
                return $list;
   }

   	 public function getAllBalls()
   {
                $balls = Balls::all();
                $list='';
                $list .='<option value="">Select Prices</option>';
                foreach($balls as $s)
                {
                   $list .='<option value="'.$s['name'].'">'.$s['name'].'</option>';
                }
                return $list;
   }


}


?>
