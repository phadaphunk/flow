<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;

class JobEventListener
{
    public function handleJobProcessing(JobProcessing $event): void
    {
        $workerId = $this->getAvailableWorker();
        if (!$workerId) return;

        $jobData = [
            'id' => $event->job->getJobId(),
            'class' => $event->job->getName(),
            'queue' => $event->job->getQueue(),
            'started_at' => now()->toISOString(),
            'status' => 'processing',
        ];

        // Update worker status to active with current job
        $workerData = Cache::get("flow:worker:{$workerId}", []);
        $workerData['status'] = 'active';
        $workerData['current_job'] = $jobData;
        Cache::put("flow:worker:{$workerId}", $workerData, 3600);

        // Store which worker is processing this job
        Cache::put("flow:job:{$event->job->getJobId()}:worker", $workerId, 600);
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $workerId = Cache::get("flow:job:{$event->job->getJobId()}:worker");
        if (!$workerId) return;

        $workerData = Cache::get("flow:worker:{$workerId}", []);
        $currentJob = $workerData['current_job'] ?? null;

        if ($currentJob) {
            $jobData = [
                'id' => $event->job->getJobId(),
                'class' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'started_at' => $currentJob['started_at'],
                'completed_at' => now()->toISOString(),
                'status' => 'completed',
                'processing_time' => $this->calculateProcessingTime($currentJob['started_at']),
                'attempts' => $event->job->attempts(),
            ];

            // Add to worker's job history
            $this->addJobToHistory($workerId, $jobData);
        }

        // Update worker status back to idle
        $workerData['status'] = 'idle';
        $workerData['current_job'] = null;
        $workerData['jobs_processed'] = ($workerData['jobs_processed'] ?? 0) + 1;
        Cache::put("flow:worker:{$workerId}", $workerData, 3600);

        // Clean up job-worker mapping
        Cache::forget("flow:job:{$event->job->getJobId()}:worker");
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $workerId = Cache::get("flow:job:{$event->job->getJobId()}:worker");
        if (!$workerId) return;

        $workerData = Cache::get("flow:worker:{$workerId}", []);
        $currentJob = $workerData['current_job'] ?? null;

        if ($currentJob) {
            $jobData = [
                'id' => $event->job->getJobId(),
                'class' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'started_at' => $currentJob['started_at'],
                'failed_at' => now()->toISOString(),
                'status' => 'failed',
                'processing_time' => $this->calculateProcessingTime($currentJob['started_at']),
                'attempts' => $event->job->attempts(),
                'error' => $event->exception->getMessage(),
                'error_class' => get_class($event->exception),
            ];

            // Add to worker's job history
            $this->addJobToHistory($workerId, $jobData);
        }

        // Update worker status back to idle
        $workerData['status'] = 'idle';
        $workerData['current_job'] = null;
        Cache::put("flow:worker:{$workerId}", $workerData, 3600);

        // Clean up job-worker mapping
        Cache::forget("flow:job:{$event->job->getJobId()}:worker");
    }

    protected function getAvailableWorker(): ?string
    {
        // Find the first idle worker
        for ($i = 1; $i <= 10; $i++) {
            $workerId = "flow_worker_{$i}";
            $workerData = Cache::get("flow:worker:{$workerId}");
            
            if ($workerData && ($workerData['status'] ?? 'idle') === 'idle') {
                return $workerId;
            }
        }
        
        // If no idle worker, use the first available worker
        for ($i = 1; $i <= 10; $i++) {
            $workerId = "flow_worker_{$i}";
            $workerData = Cache::get("flow:worker:{$workerId}");
            
            if ($workerData) {
                return $workerId;
            }
        }

        return null;
    }

    protected function calculateProcessingTime(string $startedAt): ?int
    {
        try {
            $startTime = \Carbon\Carbon::parse($startedAt);
            return $startTime->diffInMilliseconds(now());
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function addJobToHistory(string $workerId, array $jobData): void
    {
        $history = Cache::get("flow:worker:{$workerId}:history", []);
        
        // Add new job to the beginning of history
        array_unshift($history, $jobData);
        
        // Keep only the last 50 jobs per worker
        $history = array_slice($history, 0, 50);
        
        Cache::put("flow:worker:{$workerId}:history", $history, 3600);
    }
}