<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SecureUploadService
{
    /**
     * Standard EICAR test string signature for antivirus hook testing.
     */
    public const EICAR_SIGNATURE = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /**
     * Disallowed executable or dangerous extensions.
     */
    protected const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'sh', 'bash', 'zsh', 'exe', 'com', 'bat', 'cmd', 'vbs', 'msi',
        'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'jspx',
        'html', 'htm', 'xhtml', 'js', 'jar', 'war',
    ];

    /**
     * Whitelist of safe MIME types and corresponding extensions for LMS uploads.
     */
    protected const SAFE_MIME_MAP = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/zip' => ['zip'],
        'video/mp4' => ['mp4'],
        'audio/mpeg' => ['mp3'],
        'text/plain' => ['txt', 'csv'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    /**
     * Inspect and validate an uploaded file.
     *
     * @param  array<string>|null  $allowedMimes
     * @return array{safe: bool, mime: string, extension: string, size: int}
     */
    public function inspect(UploadedFile $file, int $maxSizeBytes = 25 * 1024 * 1024, ?array $allowedMimes = null): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Uploaded file is invalid or corrupted during transfer.');
        }

        // 1. Check size limit
        $size = $file->getSize();
        if ($size > $maxSizeBytes) {
            throw ValidationException::withMessages([
                $file->getClientOriginalName() => 'The uploaded file exceeds the maximum allowed size of '.round($maxSizeBytes / 1024 / 1024, 1).' MB.',
            ]);
        }

        // 2. Validate client-supplied extension
        $clientExt = strtolower($file->getClientOriginalExtension());
        if (in_array($clientExt, self::DANGEROUS_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $file->getClientOriginalName() => "Files with extension '.{$clientExt}' are prohibited for security reasons.",
            ]);
        }

        // 3. MIME sniffing using PHP finfo on actual binary bytes
        $realMime = $this->sniffMimeType($file->getRealPath());

        $allowedMimes ??= array_keys(self::SAFE_MIME_MAP);

        if (! in_array($realMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $file->getClientOriginalName() => "Detected file content ({$realMime}) does not match allowed file types.",
            ]);
        }

        // Ensure extension matches actual binary MIME
        $validExtsForMime = self::SAFE_MIME_MAP[$realMime] ?? [];
        if (! empty($validExtsForMime) && ! in_array($clientExt, $validExtsForMime, true)) {
            throw ValidationException::withMessages([
                $file->getClientOriginalName() => "File extension '.{$clientExt}' does not match its detected content type ({$realMime}).",
            ]);
        }

        // 4. Antivirus hook check
        $this->scanForViruses($file->getRealPath(), $file->getClientOriginalName());

        return [
            'safe' => true,
            'mime' => $realMime,
            'extension' => $clientExt,
            'size' => $size,
        ];
    }

    /**
     * Inspect file headers using finfo.
     */
    public function sniffMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Failed to initialize finfo for MIME sniffing.');
        }

        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mime !== false ? $mime : 'application/octet-stream';
    }

    /**
     * Antivirus Scanner Hook.
     * Inspects for known malware signatures (EICAR) or calls external ClamAV if configured.
     */
    public function scanForViruses(string $filePath, string $originalName = 'upload'): void
    {
        // Check for EICAR standard test signature or embedded script markers in non-script files
        $contents = @file_get_contents($filePath, false, null, 0, 8192);

        if ($contents !== false) {
            if (str_contains($contents, self::EICAR_SIGNATURE)) {
                throw ValidationException::withMessages([
                    $originalName => 'Antivirus scan failed: file contains known malware signature (EICAR-Test).',
                ]);
            }

            // Embedded PHP tags inside image/binary uploads
            if (str_contains(strtolower($contents), '<?php') || str_contains(strtolower($contents), '<script')) {
                throw ValidationException::withMessages([
                    $originalName => 'Malicious executable payload detected inside file headers.',
                ]);
            }
        }

        // Optional external ClamAV daemon integration via socket or clamscan command
        if (config('services.antivirus.enabled', false) && file_exists('/usr/bin/clamscan')) {
            $escapedPath = escapeshellarg($filePath);
            exec("/usr/bin/clamscan --no-summary {$escapedPath}", $output, $returnCode);

            if ($returnCode !== 0) {
                throw ValidationException::withMessages([
                    $originalName => 'File rejected by ClamAV scanner.',
                ]);
            }
        }
    }
}
