<?php
namespace App\Services;

use Cache, Log;

Class CachingService{

    public function flushAll(){
        Cache::flush();
        $data = ['status' => 200, 'message' => 'All is well'];
        \Log::info('CachingService flushAll ===> ' . json_encode($data));
    }
    
    public function flushTag($tag){
        try {
            if (!empty($tag)) {
                Cache::tags($tag)->flush();
            } else {
                $data = ['status' => 200, 'message' => 'Tag should not be null'];
                \Log::info('CachingService flushTag ===>  ' . json_encode($data));
            }
        }catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('CachingService flushTag Error ===>', $message);
        }
    }
    
    public function flushTagKey($tag, $key){
        try {
            if (!empty($tag) && !empty($key)) {
                Cache::tags($tag)->forget($key);
                $response = array('status' => 200);
            } else {
                Log::info('flushTagKey : Error ', 'Tag or Key should not be null;');
            }
        } catch (\Exception $e) {
            $message = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('CachingService flushTagKey Error ===>', $message);
        }
    }



}