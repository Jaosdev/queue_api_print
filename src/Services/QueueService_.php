<?php
namespace App\Services;

class QueueService
{
    private const QUEUE_FILE = __DIR__ . '/../../queue/print_queue.json';

    public static function loadQueue(): array
    {
        if (!file_exists(self::QUEUE_FILE)) {
            file_put_contents(self::QUEUE_FILE, json_encode([]));
        }

        $data = file_get_contents(self::QUEUE_FILE);
        return json_decode($data, true) ?? [];
    }

    public static function saveQueue(array $queue): void
    {
        file_put_contents(self::QUEUE_FILE, json_encode(array_values($queue), JSON_PRETTY_PRINT));
    }

    public static function addJob(array $job): bool
    {
        $queue = self::loadQueue();
        $job['timestamp'] = time();
        $job['status'] = 'pending';
        $job['attempts'] = 1;
        $queue[] = $job;
        self::saveQueue($queue);
        return true;
    }

    public static function deleteJob(string $filename): bool
    {
        $queue = self::loadQueue();
        $queue = array_filter($queue, function ($job) use ($filename) {
            if ($job['filename'] === $filename) {
                $path = __DIR__ . '/../../' . $job['path'];
                if (file_exists($path)) {
                    unlink($path);
                }
                return false;
            }
            return true;
        });

        self::saveQueue($queue);
        return true;
    }

    public static function getPendingJobs(): array
    {
        $queue = self::loadQueue();
        return array_values(array_filter($queue, fn($job) => $job['status'] === 'pending'));
    }

    public static function updateStatus(string $filename, string $newStatus): bool
    {
        $queue = self::loadQueue();
        foreach ($queue as &$job) {
            if ($job['filename'] === $filename) {
                $job['status'] = $newStatus;
                break;
            }
        }
        self::saveQueue($queue);
        return true;
    }
}
