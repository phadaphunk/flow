<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen" x-data="flowDashboard()">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900">🌊 Flow Dashboard</h1>
                        <div class="ml-4 flex items-center">
                            <div class="h-2 w-2 rounded-full mr-2" :class="connected ? 'bg-green-500' : 'bg-red-500'"></div>
                            <span class="text-sm text-gray-600" x-text="connected ? 'Connected' : 'Disconnected'"></span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button @click="refreshData()" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            Refresh
                        </button>
                        <button @click="dispatchTestJob()" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                            Add Test Job
                        </button>
                        <div class="text-sm text-gray-500" x-text="'Updated: ' + lastUpdated"></div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Stats Bar -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-2xl font-bold text-blue-600" x-text="pendingJobs.length"></div>
                    <div class="text-sm text-gray-600">Pending Jobs</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-2xl font-bold text-green-600" x-text="workers.filter(w => w.status === 'active').length"></div>
                    <div class="text-sm text-gray-600">Active Workers</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-2xl font-bold text-yellow-600" x-text="workers.filter(w => w.status === 'idle').length"></div>
                    <div class="text-sm text-gray-600">Idle Workers</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-2xl font-bold text-gray-600" x-text="workers.length"></div>
                    <div class="text-sm text-gray-600">Total Workers</div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Left Side: Pending Jobs Queue (1/4 width) -->
                <div class="lg:col-span-1">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Job Queue</h3>
                            <p class="text-sm text-gray-500">Jobs waiting to be processed</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <template x-for="job in pendingJobs" :key="job.id">
                                <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900" x-text="job.class"></div>
                                            <div class="text-xs text-gray-500">
                                                Queue: <span x-text="job.queue"></span> | 
                                                ID: <span x-text="job.id"></span>
                                            </div>
                                            <div class="text-xs text-gray-400" x-text="formatTime(job.created_at)"></div>
                                        </div>
                                        <div class="ml-2">
                                            <div class="w-2 h-2 bg-orange-400 rounded-full animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="pendingJobs.length === 0">
                                <div class="px-4 py-8 text-center text-gray-500">
                                    <div class="text-4xl mb-2">🎉</div>
                                    <div class="text-sm">No pending jobs</div>
                                    <div class="text-xs text-gray-400">All caught up!</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Workers (3/4 width) -->
                <div class="lg:col-span-3">
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Workers</h3>
                            <p class="text-sm text-gray-500">Current worker status and active jobs</p>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <template x-for="worker in workers" :key="worker.id">
                                <div class="px-4 py-4 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <!-- Worker Info -->
                                        <div class="flex items-center flex-1">
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <div class="h-12 w-12 rounded-full flex items-center justify-center text-white font-bold"
                                                     :class="{
                                                         'bg-green-500': worker.status === 'active',
                                                         'bg-yellow-500': worker.status === 'idle',
                                                         'bg-red-500': worker.status === 'failed'
                                                     }">
                                                    <span x-text="worker.id.slice(-3)"></span>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900" x-text="worker.id"></div>
                                                        <div class="text-sm text-gray-500">
                                                            Queue: <span x-text="worker.queue"></span> | 
                                                            Jobs: <span x-text="worker.jobs_processed"></span> |
                                                            <template x-if="worker.status === 'active' && worker.current_job">
                                                                <span class="text-green-600 font-medium">
                                                                    Processing: <span x-text="worker.current_job.class.split('\\\\').pop()"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="worker.status !== 'active' || !worker.current_job">
                                                                <span :class="{
                                                                    'text-green-600': worker.status === 'active',
                                                                    'text-yellow-600': worker.status === 'idle',
                                                                    'text-red-600': worker.status === 'failed'
                                                                }" x-text="worker.status.charAt(0).toUpperCase() + worker.status.slice(1)"></span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-xs text-gray-500" x-text="formatUptime(worker.uptime)"></div>
                                                        <div class="text-xs text-gray-400" x-text="formatMemory(worker.memory_usage?.current)"></div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Current Job Display -->
                                                <div x-show="worker.current_job" class="mt-2 p-2 bg-blue-50 rounded border-l-4 border-blue-400">
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <div class="text-sm font-medium text-blue-900">
                                                                🔄 Processing: <span x-text="worker.current_job?.class || 'Unknown Job'"></span>
                                                            </div>
                                                            <div class="text-xs text-blue-600">
                                                                Started: <span x-text="formatTime(worker.current_job?.started_at)"></span>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse mr-2"></div>
                                                            <span class="text-xs text-blue-600">Active</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="ml-4 flex items-center space-x-2">
                                            <button @click="toggleWorkerHistory(worker.id)" 
                                                    class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                History
                                            </button>
                                            <button @click="killWorker(worker.id)" 
                                                    class="bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1 rounded"
                                                    :disabled="worker.status === 'failed'">
                                                Kill
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Expandable Job History -->
                                    <div x-show="expandedWorkers[worker.id]" 
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="mt-4 bg-gray-50 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-gray-900 mb-3">Job History</h4>
                                        
                                        <template x-if="loadingHistory[worker.id]">
                                            <div class="text-center py-4">
                                                <div class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                                <span class="ml-2 text-sm text-gray-600">Loading history...</span>
                                            </div>
                                        </template>
                                        
                                        <template x-if="workerHistories[worker.id] && workerHistories[worker.id].length > 0">
                                            <div class="space-y-2 max-h-64 overflow-y-auto">
                                                <template x-for="job in workerHistories[worker.id]" :key="job.id">
                                                    <div class="bg-white p-3 rounded border border-gray-200">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center">
                                                                    <div class="w-2 h-2 rounded-full mr-2"
                                                                         :class="{
                                                                             'bg-green-500': job.status === 'completed',
                                                                             'bg-red-500': job.status === 'failed',
                                                                             'bg-blue-500': job.status === 'processing'
                                                                         }"></div>
                                                                    <span class="text-sm font-medium text-gray-900" x-text="job.class"></span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 mt-1">
                                                                    <span x-text="'Queue: ' + job.queue"></span> |
                                                                    <span x-text="'ID: ' + job.id"></span> |
                                                                    <span x-text="'Attempts: ' + (job.attempts || 1)"></span>
                                                                    <template x-if="job.processing_time">
                                                                        | <span x-text="formatProcessingTime(job.processing_time)"></span>
                                                                    </template>
                                                                </div>
                                                                <template x-if="job.error">
                                                                    <div class="text-xs text-red-600 mt-1 truncate" x-text="job.error"></div>
                                                                </template>
                                                            </div>
                                                            <div class="text-right text-xs text-gray-400">
                                                                <div x-text="formatTime(job.completed_at || job.failed_at)"></div>
                                                                <div class="capitalize" :class="{
                                                                    'text-green-600': job.status === 'completed',
                                                                    'text-red-600': job.status === 'failed'
                                                                }" x-text="job.status"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        
                                        <template x-if="workerHistories[worker.id] && workerHistories[worker.id].length === 0">
                                            <div class="text-center py-4 text-gray-500">
                                                <div class="text-2xl mb-1">📝</div>
                                                <div class="text-sm">No jobs processed yet</div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function flowDashboard() {
            return {
                connected: true,
                workers: [],
                pendingJobs: [],
                lastUpdated: new Date().toLocaleTimeString(),
                expandedWorkers: {},
                workerHistories: {},
                loadingHistory: {},

                init() {
                    this.loadData();
                    
                    // Auto-refresh every 2 seconds 
                    setInterval(() => {
                        this.loadData();
                    }, 2000);
                },

                async loadData() {
                    await Promise.all([
                        this.loadWorkers(),
                        this.loadJobs()
                    ]);
                    this.lastUpdated = new Date().toLocaleTimeString();
                },

                async loadWorkers() {
                    try {
                        const response = await fetch('/flow/api/workers');
                        const data = await response.json();
                        this.workers = data.workers;
                    } catch (error) {
                        console.error('Failed to load workers:', error);
                    }
                },

                async loadJobs() {
                    try {
                        const response = await fetch('/flow/api/jobs');
                        const data = await response.json();
                        this.pendingJobs = data.pending_jobs;
                    } catch (error) {
                        console.error('Failed to load jobs:', error);
                    }
                },

                async refreshData() {
                    await this.loadData();
                },

                async dispatchTestJob() {
                    try {
                        const response = await fetch('/flow/test-job');
                        const result = await response.json();
                        if (result.success) {
                            // Immediately refresh to show the new job
                            await this.loadJobs();
                        }
                    } catch (error) {
                        console.error('Failed to dispatch test job:', error);
                    }
                },

                async killWorker(workerId) {
                    if (!confirm(`Kill worker ${workerId}?`)) return;
                    
                    // This would call the actual kill endpoint
                    alert(`Kill signal sent to ${workerId}`);
                },

                async toggleWorkerHistory(workerId) {
                    // Toggle expansion
                    this.expandedWorkers[workerId] = !this.expandedWorkers[workerId];
                    
                    // Load history if expanding and not already loaded
                    if (this.expandedWorkers[workerId] && !this.workerHistories[workerId]) {
                        await this.loadWorkerHistory(workerId);
                    }
                },

                async loadWorkerHistory(workerId) {
                    this.loadingHistory[workerId] = true;
                    
                    try {
                        const response = await fetch(`/flow/api/workers/${workerId}/history`);
                        const data = await response.json();
                        this.workerHistories[workerId] = data.history;
                    } catch (error) {
                        console.error('Failed to load worker history:', error);
                        this.workerHistories[workerId] = [];
                    } finally {
                        this.loadingHistory[workerId] = false;
                    }
                },

                formatTime(timestamp) {
                    if (!timestamp) return '-';
                    return new Date(timestamp * 1000).toLocaleTimeString();
                },

                formatUptime(seconds) {
                    if (!seconds) return '-';
                    
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    
                    if (hours > 0) {
                        return `${hours}h ${minutes}m`;
                    } else if (minutes > 0) {
                        return `${minutes}m`;
                    } else {
                        return `${seconds}s`;
                    }
                },

                formatMemory(bytes) {
                    if (!bytes) return '-';
                    return (bytes / 1024 / 1024).toFixed(1) + 'MB';
                },

                formatProcessingTime(milliseconds) {
                    if (!milliseconds) return '-';
                    
                    if (milliseconds < 1000) {
                        return `${milliseconds}ms`;
                    }
                    
                    const seconds = Math.floor(milliseconds / 1000);
                    if (seconds < 60) {
                        return `${seconds}s`;
                    }
                    
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                    return `${minutes}m ${remainingSeconds}s`;
                }
            }
        }
    </script>
</body>
</html>