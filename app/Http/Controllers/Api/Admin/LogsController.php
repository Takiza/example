<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogsController extends BaseController
{
    /**
     * Get broker logs
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getBrokerResponse(Request $request)
    {
        $date = Carbon::make($request->date)->format('Y-m-d');

        $path = storage_path('logs/lead-response/lead-'.$date.'.log');

        if(File::exists($path)) {
            return $this->sendResponse(File::get($path), 'Log');
        } else {
            return $this->sendError('Log not found');
        }
    }
}









//--------------------------------------------------------------------

//$date = new Carbon($request->input('date'));
////        $date = Carbon::create($request->date);
////        dd($date);
//$filePath = storage_path("logs/laravel-{$date->format('Y-m-d')}.log");
//
//if(file_exists($filePath)) {
////            dd('File Exists ');
//
//    $data = file_get_contents($filePath);
////            dd($data);
//    return response()->json($data);
////            $logsData = [
////                'lastModified' => Carbon::create(File::lastModified($filePath)),
////                'size' => File::size($filePath),
////                'file' => File::get($filePath),
////            ];
//} else {
//    return response()->json('File not found', 404);
////            dd('no ');
//}
//
////        return response()->json($logsData);
////        return view('logs', compact('date', 'logsData'));
