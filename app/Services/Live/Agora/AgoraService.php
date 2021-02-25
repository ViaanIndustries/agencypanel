<?php

namespace App\Services\Live\Agora;

use App\Services\Live\Agora\Lib\AccessToken;
use App\Services\Live\Agora\AgoraRest as Rest;
use Carbon;

Class AgoraService {


    public function __construct(AccessToken $AccessToken,  AgoraRest $AgoraRest)
    {
	$this->AgoraRest = $AgoraRest;
        $this->AccessToken = $AccessToken;
    }



    public function getAccessToken($params = array())
    {

        $error_messages         =   array();
        $results                =   array();

        $artist_id              =   (!empty($params['artist_id']))   ?   $params['artist_id']:  "";

        if($artist_id != ""){

            switch ($artist_id){

                //BollyFame
                case "5d3ee748929d960e7d388ee2":
                    $appID                  =   '34a153aa097248479ae6fa9e2183b94c';
                    $appCertificate         =   '397a0c4f943b432aabd1ce4c87ff01a1';
                    break;

                //BollyFame
                default:
                    $appID                  =   '34a153aa097248479ae6fa9e2183b94c';
                    $appCertificate         =   '397a0c4f943b432aabd1ce4c87ff01a1';

            }

                $channelName            =   (!empty($params['channel']))        ?   $params['channel']: "test";
                $customer_id            =   (!empty($params['customer_id']))    ?   $params['customer_id']: time();
                $uid                    =   time();
                $expireTimestamp        =   0;
                $builder                =   $this->AccessToken->init($appID, $appCertificate, $channelName, $uid);

                $builder->addPrivilege(AccessToken::Privileges["kJoinChannel"], $expireTimestamp);
                $token                  =   $builder->build();

                $results                =   [
                    "token"  =>  $token,
                    "appID" => $appID,
                    "appCertificate" => $appCertificate,
                    "channel" => $channelName,
                    "uid" => $uid,
                    "artist_id" =>  $artist_id
                ];

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


    public function getAccessTokenV1($params = array())
    {

        $error_messages         =   array();
        $results                =   array();

        $artist_id              =   (!empty($params['artist_id']))   ?   $params['artist_id']:  "";

        if($artist_id != ""){

            switch ($artist_id){

                //BollyFame
                case "5d3ee748929d960e7d388ee2":
                    $appID                  =   '60646ca677ae4bb09ab1feb25d3bf173';
                    $appCertificate         =   '47abeb302c3147b098e479b38c9d273f';
                    break;


                //BollyFame
                default:
                    $appID                  =   '60646ca677ae4bb09ab1feb25d3bf173';
                    $appCertificate         =   '47abeb302c3147b098e479b38c9d273f';

            }

                $channelName            =   (!empty($params['channel']))        ?   $params['channel']: "test";
                $customer_id            =   (!empty($params['customer_id']))    ?   $params['customer_id']: time();
                $uid                    =   time();
                $expireTimestamp        =   0;
                $builder                =   $this->AccessToken->init($appID, $appCertificate, $channelName, $uid);

                $builder->addPrivilege(AccessToken::Privileges["kJoinChannel"], $expireTimestamp);
                $token                  =   $builder->build();

                $results                =   [
                    "token"  =>  $token,
                    "appID" => $appID,
                    "appCertificate" => $appCertificate,
                    "channel" => $channelName,
                    "uid" => $uid,
                    "artist_id" =>  $artist_id
                ];

        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }

    public function getAgoraLiveChannelList()
    {
         $error_messages=[];
    	 $finalData = [];
	     $Livedata = [];
        $postData=[];
        $results = \App\Models\Live::where('is_live',true)->get();//->toArray();
        $data = $results->toArray();
        if(!empty($data))
        {

            foreach($data as $values)
            {
                $channelData  = \App\Models\Artistconfig::where('artist_id',$values['artist_id'])->pluck('channel_namespace');
                $postData['channel_namespace'] = $channelData;
                $channelResp = json_decode($this->AgoraRest->getProducerChannels($postData));

                if($channelResp->success == true && $channelResp->data->channel_exist == false ) //&& empty($channelResp->data->broadcasters) ) //&& empty($channelResp->data->broadcasters))
		{
			
                    $timeFirst = strtotime($values['start_at']);
                    $timeSecond = strtotime(Carbon::now());
                    $seconds = $timeSecond - $timeFirst;
                    $differenceInSeconds = "";
                          /*** get the hours ***/
                    $hours = (intval($seconds) / 3600) % 24;
                    if ($hours > 0) {
                        $differenceInSeconds .= "$hours:";
		    }else
		    {
			 $differenceInSeconds .= "00:";
		    }
                  /*** get the minutes ***/
                    $minutes = (intval($seconds) / 60) % 60;
                    if ($minutes > 0) {
                        $differenceInSeconds .= "$minutes:";
		    }else
		    {
			 $differenceInSeconds .= "00:";
		    }
                    /*** get the seconds ***/
                    $seconds = intval($seconds) % 60;
                    if ($seconds > 0) {
                        $differenceInSeconds .= "$seconds";
		    }else
		    {	
			 $differenceInSeconds .= "00";
		    }

		    $Livedata = \App\Models\Live::where('_id',$values['_id'])->where('is_live',true)->first();
		    if($Livedata)
		    {
                    	$originalProgram = $Livedata->stats;
	                    $originalProgram['duration'] =  $differenceInSeconds;
        	            $Livedata->stats = $originalProgram;
                	    $Livedata->is_live = false;
	                    $Livedata->is_end = true;
        	            $Livedata->end_at = Carbon::now();
                	    $Livedata->ended_by = 'cron';

                        $doller_rate = $Livedata->doller_rate;
                        $liveearn =  $Livedata->stats['coin_spent'] * $doller_rate;
                        $final = number_format($liveearn, 2, '.', '');
                        $Livedata->total_earning_doller = $final;
                        $Livedata->save();
		    }
                }
            }
         }else{

         }
        return ['error_messages' => $error_messages, 'results' => $Livedata];
    }

}
