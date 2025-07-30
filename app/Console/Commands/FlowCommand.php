<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Cache;

class FlowCommand extends Command
{
    protected $signature = 'flow {--workers=3 : Number of workers to spawn} {--queues=default : Comma-separated list of queues} {--dashboard=8080 : Dashboard port}';
    
    protected $description = 'Start Flow - Advanced queue supervisor with real-time visibility';

    protected array $workers = [];
    protected bool $running = true;

    public function handle()
    {
        $this->components->info('🌊 Flow - Advanced Queue Supervisor');
        $this->components->info('Real-time visibility into your queue workers');
        
        $workerCount = (int) $this->option('workers');
        $queues = explode(',', $this->option('queues'));
        $dashboardPort = (int) $this->option('dashboard');
        
        $this->components->info("Starting {$workerCount} workers for queues: " . implode(', ', $queues));
        $this->components->info("Dashboard available at: http://flow.test/flow");
        
        // Set up signal handlers for graceful shutdown
        $this->setupSignalHandlers();
        
        // Spawn initial workers
        $this->spawnWorkers($workerCount, $queues[0]);
        
        $this->line("✅ {$workerCount} workers started successfully");
        $this->line('📊 Monitor them at: http://flow.test/flow');
        $this->line('Press Ctrl+C to stop all workers');
        
        // Main supervision loop
        $this->supervisionLoop();
    }

    protected function spawnWorkers(int $count, string $queue): void
    {
        for ($i = 0; $i < $count; $i++) {
            $workerId = 'flow_worker_' . ($i + 1);
            $this->spawnWorker($workerId, $queue);
        }
    }

    protected function spawnWorker(string $workerId, string $queue): void
    {
        $command = [
            'php',
            base_path('artisan'),
            'flow:worker',
            $workerId,
            '--queue=' . $queue,
        ];

        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->start();

        $this->workers[$workerId] = [
            'id' => $workerId,
            'queue' => $queue,
            'process' => $process,
            'started_at' => now(),
            'jobs_processed' => 0,
        ];

        // Store initial worker info in cache for dashboard
        Cache::put("flow:worker:{$workerId}", [
            'id' => $workerId,
            'queue' => $queue,
            'status' => 'idle',
            'pid' => $process->getPid(),
            'started_at' => now()->toISOString(),
            'jobs_processed' => 0,
            'current_job' => null,
            'uptime' => 0,
        ], 300);
        
        // Initialize job history for this worker
        Cache::put("flow:worker:{$workerId}:history", [], 3600);

        $this->line("  ✓ Worker {$workerId} started (PID: {$process->getPid()})");
    }

    protected function supervisionLoop(): void
    {
        while ($this->running) {
            $this->checkWorkerHealth();
            sleep(2);
        }
    }

    protected function checkWorkerHealth(): void
    {
        foreach ($this->workers as $workerId => $worker) {
            $process = $worker['process'];
            
            if (!$process->isRunning()) {
                $this->line("⚠️  Worker {$workerId} died, restarting...");
                
                // Remove from cache
                Cache::forget("flow:worker:{$workerId}");
                
                // Restart worker
                $this->spawnWorker($workerId, $worker['queue']);
            } else {
                // Update worker status in cache
                Cache::put("flow:worker:{$workerId}", [
                    'id' => $workerId,
                    'queue' => $worker['queue'],
                    'status' => 'idle', // We'll detect active status later
                    'pid' => $process->getPid(),
                    'started_at' => $worker['started_at']->toISOString(),
                    'jobs_processed' => $worker['jobs_processed'],
                    'uptime' => $worker['started_at']->diffInSeconds(now()),
                    'memory_usage' => [
                        'current' => memory_get_usage(true),
                        'peak' => memory_get_peak_usage(true),
                    ],
                ], 300);
            }
        }
    }

    protected function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'gracefulShutdown']);
            pcntl_signal(SIGINT, [$this, 'gracefulShutdown']);
        }
    }

    public function gracefulShutdown(): void
    {
        $this->running = false;
        $this->line("\n🛑 Shutting down Flow supervisor...");
        
        foreach ($this->workers as $workerId => $worker) {
            $process = $worker['process'];
            if ($process->isRunning()) {
                $this->line("  Stopping worker {$workerId}...");
                $process->signal(SIGTERM);
                
                // Wait a bit for graceful shutdown
                sleep(2);
                
                // Force kill if still running
                if ($process->isRunning()) {
                    $process->signal(SIGKILL);
                }
            }
            
            // Clean up cache
            Cache::forget("flow:worker:{$workerId}");
        }
        
        $this->line("✅ All workers stopped");
        exit(0);
    }
}