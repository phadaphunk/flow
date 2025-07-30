<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlowController extends Controller
{
    public function dashboard()
    {
        return view('flow.dashboard');
    }

    public function jobs()
    {
        // Get pending jobs from the database queue
        $pendingJobs = DB::table('jobs')
            ->select('id', 'queue', 'payload', 'created_at', 'available_at')
            ->where('available_at', '<=', now()->timestamp)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'class' => $payload['displayName'] ?? 'Unknown Job',
                    'data' => $payload['data'] ?? [],
                    'created_at' => $job->created_at,
                    'status' => 'pending'
                ];
            });

        return response()->json([
            'pending_jobs' => $pendingJobs,
            'stats' => [
                'total_pending' => $pendingJobs->count(),
                'by_queue' => $pendingJobs->groupBy('queue')->map->count(),
            ]
        ]);
    }

    public function workers()
    {
        $workers = [];
        
        // Get all Flow workers from cache
        $cacheKeys = \Illuminate\Support\Facades\Cache::get('flow:worker_keys', []);
        
        // If no keys stored, try to find them by pattern (fallback)
        if (empty($cacheKeys)) {
            for ($i = 1; $i <= 10; $i++) { // Check up to 10 workers
                $workerId = "flow_worker_{$i}";
                $workerData = \Illuminate\Support\Facades\Cache::get("flow:worker:{$workerId}");
                if ($workerData) {
                    $workers[] = $workerData;
                }
            }
        } else {
            // Use stored keys
            foreach ($cacheKeys as $workerId) {
                $workerData = \Illuminate\Support\Facades\Cache::get("flow:worker:{$workerId}");
                if ($workerData) {
                    $workers[] = $workerData;
                }
            }
        }

        return response()->json([
            'workers' => $workers,
            'stats' => [
                'total' => count($workers),
                'active' => collect($workers)->where('status', 'active')->count(),
                'idle' => collect($workers)->where('status', 'idle')->count(),
                'failed' => collect($workers)->where('status', 'failed')->count(),
            ]
        ]);
    }

    public function workerHistory(string $workerId)
    {
        $history = \Illuminate\Support\Facades\Cache::get("flow:worker:{$workerId}:history", []);
        
        return response()->json([
            'worker_id' => $workerId,
            'history' => $history,
            'total_jobs' => count($history),
        ]);
    }
}