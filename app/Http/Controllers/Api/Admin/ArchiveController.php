<?php

namespace App\Http\Controllers\Api\Admin;

use App\Components\GitLab;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\ArchiveCreateRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\Archive;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ArchiveController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $items = Archive::select([
            'id',
            'name',
            'git_path',
            'archive_type_id'
        ])
            ->with([
                'archive_type:id,name',
            ])
            ->orderBy('id', 'DESC')
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Archives collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  ArchiveCreateRequest $request
     * @return JsonResponse
     */
    public function store(ArchiveCreateRequest $request)
    {
        $data = $request->input();

        $token = Setting::where('name', 'git_token')->first();
        if(!$token) {
            return $this->sendError('Git token not found', null, 404);
        }

        $file = $request->file('archive');

//        $fullName = $data['name'];
        $fullName = $file->getClientOriginalName();
        $fullName = pathinfo($fullName, PATHINFO_FILENAME);

        $result = GitLab::createProject($fullName, $token->value);

        if (isset($result->ssh_url_to_repo)) {
            $data['name'] = $fullName;
            $data['git_path'] = $result->ssh_url_to_repo;

            $path = $file->store('local');

            $tempDir = sys_get_temp_dir();

            $zip = new \ZipArchive();
            $storagePath = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

            if ($zip->open($storagePath . $path) === TRUE) {

                $zip->extractTo($tempDir);
                $zip->close();

                // remove zip file
                Storage::delete($path);

                // push to git
                $folderPath = $tempDir . '/' . $fullName;

                if (env('APP_ENV') == 'production') {
                    $cloneURL = str_replace('gitlab.com', 'gitlab.com-work', $result->ssh_url_to_repo);
                } else {
                    $cloneURL = $result->ssh_url_to_repo;
                }

                $commands = "cd $folderPath && git init && git remote add origin $cloneURL && git add . && git commit -m \"Init\" && git push -u origin master && rm -rf $folderPath";

                exec($commands);
            } else {
                return $this->sendError('Error unzip archive.', null, 400);

            }
        } else {
            return $this->sendError('Error upload to git.', null, 400);
        }


        $item = Archive::create($data);

        if($item){
            $item = Archive::select([
                'id',
                'name',
                'git_path',
                'archive_type_id'
            ])
                ->with([
                    'archive_type:id,name',
                ])
                ->find($item->id);

            return $this->sendResponse($item, 'Archive created successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $result = Archive::destroy($id);

        if (!$result) {
            return $this->sendError('Archive not found');
        } else {
            return $this->sendResponse($id, 'Archive removed');
        }
    }
}
