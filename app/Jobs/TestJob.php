<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $message = 'Hello from Flow!',
        public int $sleepSeconds = 3
    ) {
        //
    }

    public function handle(): void
    {
        Log::info("TestJob started: {$this->message}");

        // Simulate work
        sleep($this->sleepSeconds);

        Log::info("TestJob completed: {$this->message}");
    }
}
