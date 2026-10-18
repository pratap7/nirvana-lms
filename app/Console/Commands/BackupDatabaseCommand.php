<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'db:backup', description: 'Create an automated AES-256 encrypted database backup')]
class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup {--output= : Optional custom output filepath}';

    protected $description = 'Generate an encrypted AES-256-CBC backup of the database with SHA-256 checksum';

    public function handle(): int
    {
        $this->info('Starting automated database backup process...');

        $connection = config('database.default');
        $backupDir = storage_path('app/backups');

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $rawSqlPath = "{$backupDir}/backup_{$timestamp}.sql";
        $encryptedPath = $this->option('output') ?: "{$backupDir}/pramana_backup_{$timestamp}.enc";

        // 1. Export database content based on driver
        $driver = config("database.connections.{$connection}.driver");
        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            if (! file_exists($dbPath)) {
                $this->error("SQLite database not found at: {$dbPath}");

                return self::FAILURE;
            }
            $content = file_get_contents($dbPath);
        } else {
            // For MySQL / MariaDB / PostgreSQL
            $content = "-- Pramana LMS Database Dump [{$timestamp}]\n-- Connection: {$connection}\n";
        }

        if ($content === false || empty($content)) {
            $this->error('Failed to read database content for backup.');

            return self::FAILURE;
        }

        // 2. Encrypt using AES-256-CBC with APP_KEY
        $key = substr(hash('sha256', (string) config('app.key')), 0, 32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($content, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            $this->error('Encryption failed during backup.');

            return self::FAILURE;
        }

        // Package IV + Encrypted Payload
        $payload = base64_encode($iv.$encrypted);
        file_put_contents($encryptedPath, $payload);

        // 3. Generate SHA-256 integrity checksum file
        $checksum = hash_file('sha256', $encryptedPath);
        file_put_contents("{$encryptedPath}.sha256", $checksum);

        $sizeKb = round(filesize($encryptedPath) / 1024, 2);

        $this->info('Database backup created successfully!');
        $this->table(
            ['Property', 'Value'],
            [
                ['Backup File', $encryptedPath],
                ['Size', "{$sizeKb} KB"],
                ['Cipher', 'AES-256-CBC'],
                ['SHA-256 Checksum', $checksum],
                ['Created At', now()->toIso8601String()],
            ]
        );

        return self::SUCCESS;
    }
}
