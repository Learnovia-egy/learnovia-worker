<?php

namespace App\Http\Controllers;

use App\Jobs\CloudChunkingProcessJob;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class VideoChunkingController extends Controller
{

    public function __construct()
    {

    }

    /**
     * Store a newly created resource in storage.
     * @throws \Exception
     */
    public function process(Request $request)
    {
        $request->validate([
            'video_id' => 'required|string',
            'media_path' => 'required|string',
            'client_base_url' => 'required|string',
            'client_name' => 'required|string'
        ]);

        $client = Client::updateOrCreate(['name' => $request->client_name, 'base_url' => $request->client_base_url]);
        Storage::disk('local')->makeDirectory($client->name);

        Cache::put('client_base_url_' . $request->video_id, ['base_url'=>$client->base_url, 'name'=>$client->name]);

        $clientVideo = $client->clientVideos()->create(['video_id' => $request->video_id, 'media_path' => $request->media_path]);
        // --- http send to PROCESS-VIDEO SERVER ----
        CloudChunkingProcessJob::dispatch($clientVideo->id);
        return response()->json(['message' => 'Video chunking process started']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
