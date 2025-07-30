<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Flow Dashboard Routes
Route::get('flow', [App\Http\Controllers\FlowController::class, 'dashboard'])->name('flow.dashboard');
Route::get('flow/api/jobs', [App\Http\Controllers\FlowController::class, 'jobs']);
Route::get('flow/api/workers', [App\Http\Controllers\FlowController::class, 'workers']);
Route::get('flow/api/workers/{workerId}/history', [App\Http\Controllers\FlowController::class, 'workerHistory']);

// Test route to dispatch jobs
Route::get('flow/test-job', function () {
    $messages = ['Process data', 'Send email', 'Generate report', 'Cleanup files', 'Update cache'];
    $message = $messages[array_rand($messages)];
    $seconds = rand(3, 10);
    
    App\Jobs\TestJob::dispatch($message, $seconds);
    
    return response()->json([
        'success' => true,
        'message' => "Dispatched: {$message} (will take {$seconds}s)"
    ]);
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
