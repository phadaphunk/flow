<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FlowWorkerCommand extends Command
{
    protected $signature = 'flow:worker {workerId} {--queue=default}';
    
    protected $description = 'Flow worker process that tracks its own job processing';

    protected string $workerId;
    protected string $queue;

    public function handle()
    {
        $this->workerId = $this->argument('workerId');
        $this->queue = $this->option('queue');
        
        // Initialize worker status
        $this->updateWorkerStatus('idle', null);
        
        $this->line("🚀 [{$this->workerId}] Worker started, processing queue: {$this->queue}");
        
        // Set up job event listeners for ALL workers globally
        $this->setupGlobalJobEventListeners();
        
        // Use Laravel's queue:work command
        $this->call('queue:work', [
            '--queue' => $this->queue,
            '--name' => $this->workerId,
            '--tries' => 3,
            '--timeout' => 60,
            '--sleep' => 3,
        ]);
    }

    protected function setupGlobalJobEventListeners(): void
    {
        // Create a simple job counter for each worker
        static $jobCounter = 0;
        static $workerAssignments = [];
        
        // Job started processing - assign to next available worker
        app('events')->listen('Illuminate\Queue\Events\JobProcessing', function ($event) use (&$jobCounter, &$workerAssignments) {
            $jobId = $event->job->getJobId();
            
            // Find next idle worker
            $assignedWorker = $this->getNextIdleWorker();
            $workerAssignments[$jobId] = $assignedWorker;
            
            $jobData = [
                'id' => $jobId,
                'class' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'started_at' => now()->toISOString(),
                'attempts' => $event->job->attempts(),
            ];

            // Update assigned worker status
            if ($assignedWorker) {
                $this->updateWorkerStatusForId($assignedWorker, 'active', $jobData);
                $this->line("🔄 [{$assignedWorker}] Processing: {$jobData['class']}");
            }
        });

        // Job completed successfully
        app('events')->listen('Illuminate\Queue\Events\JobProcessed', function ($event) use (&$workerAssignments) {
            $jobId = $event->job->getJobId();
            $assignedWorker = $workerAssignments[$jobId] ?? null;
            
            if ($assignedWorker) {
                $currentJob = $this->getCurrentJobForWorker($assignedWorker);
                
                if ($currentJob) {
                    $jobData = [
                        'id' => $jobId,
                        'class' => $event->job->getName(),
                        'queue' => $event->job->getQueue(),
                        'started_at' => $currentJob['started_at'],
                        'completed_at' => now()->toISOString(),
                        'status' => 'completed',
                        'processing_time' => $this->calculateProcessingTime($currentJob['started_at']),
                        'attempts' => $event->job->attempts(),
                    ];

                    $this->addJobToHistoryForWorker($assignedWorker, $jobData);
                    $this->incrementJobsProcessedForWorker($assignedWorker);
                    
                    $this->line("✅ [{$assignedWorker}] Completed: {$jobData['class']} ({$jobData['processing_time']}ms)");
                }

                $this->updateWorkerStatusForId($assignedWorker, 'idle', null);
                unset($workerAssignments[$jobId]);
            }
        });

        // Job failed
        app('events')->listen('Illuminate\Queue\Events\JobFailed', function ($event) use (&$workerAssignments) {
            $jobId = $event->job->getJobId();
            $assignedWorker = $workerAssignments[$jobId] ?? null;
            
            if ($assignedWorker) {
                $currentJob = $this->getCurrentJobForWorker($assignedWorker);
                
                if ($currentJob) {
                    $jobData = [
                        'id' => $jobId,
                        'class' => $event->job->getName(),
                        'queue' => $event->job->getQueue(),
                        'started_at' => $currentJob['started_at'],
                        'failed_at' => now()->toISOString(),
                        'status' => 'failed',
                        'processing_time' => $this->calculateProcessingTime($currentJob['started_at']),
                        'attempts' => $event->job->attempts(),
                        'error' => $event->exception->getMessage(),
                    ];

                    $this->addJobToHistoryForWorker($assignedWorker, $jobData);
                    
                    $this->line("❌ [{$assignedWorker}] Failed: {$jobData['class']} - {$jobData['error']}");
                }

                $this->updateWorkerStatusForId($assignedWorker, 'idle', null);
                unset($workerAssignments[$jobId]);
            }
        });
    }

    protected function getNextIdleWorker(): ?string
    {
        // Find first idle worker
        for ($i = 1; $i <= 10; $i++) {
            $workerId = "flow_worker_{$i}";
            $workerData = Cache::get("flow:worker:{$workerId}");
            
            if ($workerData && ($workerData['status'] ?? 'idle') === 'idle') {
                return $workerId;
            }
        }
        
        // If no idle worker, return first available worker
        for ($i = 1; $i <= 10; $i++) {
            $workerId = "flow_worker_{$i}";
            $workerData = Cache::get("flow:worker:{$workerId}");
            
            if ($workerData) {
                return $workerId;
            }
        }
        
        return null;
    }

    protected function updateWorkerStatus(string $status, ?array $currentJob): void
    {
        $this->updateWorkerStatusForId($this->workerId, $status, $currentJob);
    }
    
    protected function updateWorkerStatusForId(string $workerId, string $status, ?array $currentJob): void
    {
        $workerData = Cache::get("flow:worker:{$workerId}", []);
        
        $workerData = array_merge($workerData, [
            'id' => $workerId,
            'queue' => $this->queue,
            'status' => $status,
            'current_job' => $currentJob,
            'last_heartbeat' => now()->toISOString(),
            'uptime' => isset($workerData['started_at']) ? 
                now()->diffInSeconds($workerData['started_at']) : 0,
        ]);

        Cache::put("flow:worker:{$workerId}", $workerData, 300);
    }

    protected function getCurrentJobForWorker(string $workerId): ?array
    {
        $workerData = Cache::get("flow:worker:{$workerId}", []);
        return $workerData['current_job'] ?? null;
    }

    protected function calculateProcessingTime(string $startedAt): int
    {
        try {
            $startTime = \Carbon\Carbon::parse($startedAt);
            return $startTime->diffInMilliseconds(now());
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function addJobToHistoryForWorker(string $workerId, array $jobData): void
    {
        $history = Cache::get("flow:worker:{$workerId}:history", []);
        
        // Add to beginning of history
        array_unshift($history, $jobData);
        
        // Keep only last 100 jobs
        $history = array_slice($history, 0, 100);
        
        Cache::put("flow:worker:{$workerId}:history", $history, 3600);
    }

    protected function incrementJobsProcessedForWorker(string $workerId): void
    {
        $workerData = Cache::get("flow:worker:{$workerId}", []);
        $workerData['jobs_processed'] = ($workerData['jobs_processed'] ?? 0) + 1;
        Cache::put("flow:worker:{$workerId}", $workerData, 300);
    }
}