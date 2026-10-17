<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => '1.0.0',
            'services' => $checks,
        ], $isHealthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, message: string, latency_ms?: float}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'ok' => true,
                'message' => 'Connected successfully',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkCache(): array
    {
        try {
            $key = '_health_check_'.uniqid();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val !== 'ok') {
                return [
                    'ok' => false,
                    'message' => 'Cache write verification failed',
                ];
            }

            return [
                'ok' => true,
                'message' => 'Cache read/write operating normally',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Cache service error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkStorage(): array
    {
        try {
            $testFile = '_health_check_'.uniqid().'.txt';
            Storage::disk('local')->put($testFile, 'ok');
            Storage::disk('local')->delete($testFile);

            return [
                'ok' => true,
                'message' => 'Storage disk is writable',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Storage disk write failed: '.$e->getMessage(),
            ];
        }
    }
}
