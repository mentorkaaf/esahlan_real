<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class FileUploadSecurityService
{
    // Extensions that must never be stored, regardless of claimed MIME type
    const BLOCKED_EXTENSIONS = [
        'php','phtml','php3','php4','php5','php7','php8','phar',
        'cgi','pl','py','rb','sh','bash','cmd','bat','ps1','vbs',
        'htaccess','htpasswd','exe','dll','so','elf',
        'jsp','jspx','asp','aspx','cfm','shtml',
    ];

    // Magic byte signatures: [offset, hex_bytes, mime_type]
    const SIGNATURES = [
        [0, 'FFD8FF',           'image/jpeg'],
        [0, '89504E47',         'image/png'],
        [0, '47494638',         'image/gif'],       // GIF8
        [0, '52494646',         'image/webp'],      // RIFF….WEBP (checked separately)
        [0, '00000018667479',   'video/mp4'],       // ftyp at offset 4 via 0-prefix variant
        [0, '000000206674',     'video/mp4'],
        [0, '1A45DFA3',         'video/webm'],
        [4, '6674797069736F6D', 'video/mp4'],       // ftypisom
        [4, '6674797033677035', 'video/mp4'],       // ftyp3gp5
        [4, '667479704D534E56', 'video/mp4'],       // ftypMSNV
        [4, '66747970',         'video/mp4'],       // ftyp — generic mp4/mov
        [0, 'FFFB',             'audio/mpeg'],
        [0, 'FFF3',             'audio/mpeg'],
        [0, 'FFF2',             'audio/mpeg'],
        [0, '494433',           'audio/mpeg'],      // ID3
        [0, '25504446',         'application/pdf'], // %PDF
        [0, 'D0CF11E0',         'application/msword'], // OLE2 (doc, xls)
        [0, '504B0304',         'application/zip'], // ZIP (docx is a zip)
    ];

    // Magic bytes of content types that must NEVER be allowed in media uploads
    const MALICIOUS_SIGNATURES = [
        [0, '3C3F706870',   '<?php tag'],
        [0, '3C3F',         '<? short tag'],
        [0, '4D5A',         'Windows PE executable'],
        [0, '7F454C46',     'ELF executable'],
        [0, '2321',         'Shell script (#!)'],
        [0, '504B',         null],                  // ZIP — only allowed for documents
    ];

    // Max size in bytes per category
    const SIZE_LIMITS = [
        'image'    => 10  * 1024 * 1024,  // 10 MB
        'video'    => 150 * 1024 * 1024,  // 150 MB
        'audio'    => 20  * 1024 * 1024,  // 20 MB
        'document' => 20  * 1024 * 1024,  // 20 MB
        'avatar'   => 5   * 1024 * 1024,  // 5 MB
    ];

    /**
     * Validate a file upload. Returns ['ok' => true] or ['ok' => false, 'reason' => '…'].
     *
     * @param string $allowedCategory  image | video | audio | document | avatar
     *                                 Pass 'media' to allow image+video+audio+document.
     */
    public static function validate(UploadedFile $file, string $allowedCategory = 'media'): array
    {
        // 1. Extension check
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, self::BLOCKED_EXTENSIONS)) {
            return ['ok' => false, 'reason' => "File type .$ext is not allowed."];
        }

        // 2. Double-extension check: evil.jpg.php
        $originalName = $file->getClientOriginalName();
        $parts = explode('.', $originalName);
        foreach ($parts as $part) {
            if (in_array(strtolower($part), self::BLOCKED_EXTENSIONS)) {
                return ['ok' => false, 'reason' => 'Suspicious filename detected.'];
            }
        }

        // 3. Null byte in filename
        if (str_contains($originalName, "\0")) {
            return ['ok' => false, 'reason' => 'Invalid filename.'];
        }

        // 4. Read magic bytes (first 16 bytes sufficient for all checks)
        $path   = $file->getRealPath();
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return ['ok' => false, 'reason' => 'Cannot read uploaded file.'];
        }
        $bytes    = fread($handle, 16);
        fclose($handle);
        $hexBytes = strtoupper(bin2hex($bytes));

        // 5. Malicious signature check — reject these regardless of category
        foreach (self::MALICIOUS_SIGNATURES as [$offset, $sig, $label]) {
            $hexOffset = $offset * 2;
            if (str_starts_with(substr($hexBytes, $hexOffset), strtoupper($sig))) {
                // ZIP is only blocked in non-document uploads
                if ($sig === '504B' && str_contains($allowedCategory, 'document')) {
                    continue;
                }
                return ['ok' => false, 'reason' => 'File contains disallowed content' . ($label ? " ($label)" : '') . '.'];
            }
        }

        // 6. Category-specific MIME + magic validation
        $detectedType = self::detectMimeFromBytes($hexBytes);
        $allowed      = self::allowedMimesForCategory($allowedCategory);

        if ($detectedType && !in_array($detectedType, $allowed)) {
            return ['ok' => false, 'reason' => "File content ($detectedType) is not allowed for this upload type."];
        }

        // 7. Size limit
        $sizeCategory = self::sizeCategory($allowedCategory, $ext);
        $maxSize      = self::SIZE_LIMITS[$sizeCategory] ?? self::SIZE_LIMITS['document'];
        if ($file->getSize() > $maxSize) {
            $mb = round($maxSize / 1024 / 1024);
            return ['ok' => false, 'reason' => "File exceeds maximum size of {$mb} MB."];
        }

        return ['ok' => true];
    }

    /**
     * Sanitize filename: keep only safe characters, strip path separators.
     */
    public static function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        // Replace anything that isn't alphanumeric, dot, dash, or underscore
        $name = preg_replace('/[^\w.\-]/', '_', $name);
        // Prevent hidden files (.htaccess etc.) by stripping leading dots
        $name = ltrim($name, '.');
        return substr($name, 0, 100) ?: 'upload';
    }

    private static function detectMimeFromBytes(string $hexBytes): ?string
    {
        // WEBP special check: RIFF + WEBP at offset 8
        if (str_starts_with($hexBytes, '52494646') && str_contains(substr($hexBytes, 16, 8), '57454250')) {
            return 'image/webp';
        }

        foreach (self::SIGNATURES as [$offset, $sig, $mime]) {
            $hexOffset = $offset * 2;
            if (str_starts_with(substr($hexBytes, $hexOffset), strtoupper($sig))) {
                return $mime;
            }
        }
        return null;
    }

    private static function allowedMimesForCategory(string $category): array
    {
        return match($category) {
            'image', 'avatar' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'video'           => ['video/mp4', 'video/webm'],
            'audio'           => ['audio/mpeg'],
            'document'        => ['application/pdf', 'application/msword', 'application/zip'],
            default           => ['image/jpeg','image/png','image/gif','image/webp',
                                  'video/mp4','video/webm','audio/mpeg',
                                  'application/pdf','application/msword','application/zip'],
        };
    }

    private static function sizeCategory(string $category, string $ext): string
    {
        if ($category === 'avatar') return 'avatar';
        if (in_array($ext, ['mp4','mov','webm'])) return 'video';
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) return 'image';
        if (in_array($ext, ['mp3','m4a','ogg','wav','aac'])) return 'audio';
        return 'document';
    }
}
