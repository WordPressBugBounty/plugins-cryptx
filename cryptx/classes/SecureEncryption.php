<?php

namespace CryptX;

/**
 * Secure encryption class using modern cryptographic standards
 * Compatible with JavaScript Web Crypto API
 */
class SecureEncryption
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 16;  // 128 bits
    private const SALT_LENGTH = 16; // 128 bits
    private static int $iterations = 10000; // PBKDF2 iterations

    // Performance optimization: Key cache
    private static array $keyCache = [];
    private static int $maxCacheSize = 10; // Limit cache size to prevent memory issues

    // Performance optimization: Pre-check cipher availability
    private static ?bool $cipherAvailable = null;

    /**
     * Pre-checks if the required cipher is available
     *
     * @return bool
     */
    private static function isCipherAvailable(): bool
    {
        if (self::$cipherAvailable === null) {
            self::$cipherAvailable = function_exists('openssl_encrypt') &&
                in_array(self::CIPHER, openssl_get_cipher_methods());
        }
        return self::$cipherAvailable;
    }

    /**
     * Derives a key from password using PBKDF2 with caching - compatible with JavaScript
     *
     * @param string $password
     * @param string $salt
     * @return string
     * @throws \Exception
     */
    private static function deriveKey(string $password, string $salt): string
    {
        if (!function_exists('hash_pbkdf2')) {
            throw new \Exception('PBKDF2 not available');
        }

        // Performance optimization: Cache derived keys
        $cacheKey = hash('sha256', $password . $salt);

        if (isset(self::$keyCache[$cacheKey])) {
            return self::$keyCache[$cacheKey];
        }

        $derivedKey = hash_pbkdf2('sha256', $password, $salt, self::getIterations(), self::KEY_LENGTH, true);

        // Manage cache size to prevent memory issues
        if (count(self::$keyCache) >= self::$maxCacheSize) {
            // Remove oldest entry (FIFO)
            $oldestKey = array_key_first(self::$keyCache);
            unset(self::$keyCache[$oldestKey]);
        }

        self::$keyCache[$cacheKey] = $derivedKey;
        return $derivedKey;
    }

    /**
     * Encrypts plaintext using AES-256-GCM - JavaScript compatible format
     * Optimized for performance
     *
     * @param string $plaintext
     * @param string $password
     * @return string Base64 encoded encrypted data
     * @throws \Exception
     */
    public static function encrypt(string $plaintext, string $password): string
    {
        // Performance optimization: Pre-check cipher availability
        if (!self::isCipherAvailable()) {
            throw new \Exception('OpenSSL extension or AES-256-GCM cipher not available');
        }

        self::setIterations();

        // Performance optimization: Generate salt and IV in one call
        $randomBytes = random_bytes(self::SALT_LENGTH + self::IV_LENGTH);
        $salt = substr($randomBytes, 0, self::SALT_LENGTH);
        $iv = substr($randomBytes, self::SALT_LENGTH);

        // Derive key from password (now with caching)
        $key = self::deriveKey($password, $salt);

        // Encrypt data
        $tag = '';
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }

        // Performance optimization: Use direct concatenation instead of multiple operations
        return base64_encode($salt . $iv . $encrypted . $tag);
    }

    /**
     * Batch encrypt multiple plaintexts with same password for better performance
     *
     * @param array $plaintexts Array of strings to encrypt
     * @param string $password
     * @return array Array of encrypted strings
     * @throws \Exception
     */
    public static function encryptBatch(array $plaintexts, string $password): array
    {
        if (!self::isCipherAvailable()) {
            throw new \Exception('OpenSSL extension or AES-256-GCM cipher not available');
        }

        $results = [];

        foreach ($plaintexts as $key => $plaintext) {
            try {
                $results[$key] = self::encrypt($plaintext, $password);
            } catch (\Exception $e) {
                $results[$key] = false; // Or handle error as needed
            }
        }

        return $results;
    }

    /**
     * Clears the key cache - useful for memory management
     *
     * @return void
     */
    public static function clearKeyCache(): void
    {
        self::$keyCache = [];
    }

    /**
     * Gets current cache statistics
     *
     * @return array
     */
    public static function getCacheStats(): array
    {
        return [
            'cache_size' => count(self::$keyCache),
            'max_cache_size' => self::$maxCacheSize,
            'memory_usage_bytes' => memory_get_usage(),
        ];
    }

    /**
     * Decrypts encrypted data using AES-256-GCM - JavaScript compatible
     *
     * @param string $encryptedData Base64 encoded encrypted data
     * @param string $password
     * @return string Decrypted plaintext
     * @throws \Exception
     */
    public static function decrypt(string $encryptedData, string $password): string
    {
        if (!function_exists('openssl_decrypt')) {
            throw new \Exception('OpenSSL extension not available');
        }

        try {
            $combined = base64_decode($encryptedData, true);
            if ($combined === false) {
                throw new \Exception('Invalid base64 encoding');
            }

            $totalLength = strlen($combined);
            $expectedMinLength = self::SALT_LENGTH + self::IV_LENGTH + 16; // +16 for tag

            if ($totalLength < $expectedMinLength) {
                throw new \Exception('Encrypted data too short');
            }

            // Extract components: salt(16) + iv(16) + encrypted_data + tag(16)
            $salt = substr($combined, 0, self::SALT_LENGTH);
            $iv = substr($combined, self::SALT_LENGTH, self::IV_LENGTH);
            $encryptedDataLength = $totalLength - self::SALT_LENGTH - self::IV_LENGTH - 16;
            $encrypted = substr($combined, self::SALT_LENGTH + self::IV_LENGTH, $encryptedDataLength);
            $tag = substr($combined, -16); // Last 16 bytes

            if (strlen($salt) !== self::SALT_LENGTH ||
                strlen($iv) !== self::IV_LENGTH ||
                strlen($tag) !== 16) {
                throw new \Exception('Invalid encrypted data format');
            }

            // Derive key from password (now with caching)
            $key = self::deriveKey($password, $salt);

            // Decrypt data
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decrypted === false) {
                throw new \Exception('Decryption failed or data corrupted');
            }

            return $decrypted;
        } catch (\Throwable $e) {
            throw new \Exception('Decryption failed: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Test encryption/decryption with debug output
     *
     * @param string $plaintext
     * @param string $password
     * @return array Debug information
     */
    public static function debugEncryption(string $plaintext, string $password): array
    {
        try {
            $startTime = microtime(true);
            $encrypted = self::encrypt($plaintext, $password);
            $encryptTime = microtime(true) - $startTime;

            $startTime = microtime(true);
            $decrypted = self::decrypt($encrypted, $password);
            $decryptTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'plaintext' => $plaintext,
                'encrypted' => $encrypted,
                'decrypted' => $decrypted,
                'match' => ($plaintext === $decrypted),
                'encrypted_length' => strlen($encrypted),
                'binary_length' => strlen(base64_decode($encrypted)),
                'encrypt_time_ms' => round($encryptTime * 1000, 2),
                'decrypt_time_ms' => round($decryptTime * 1000, 2),
                'cache_stats' => self::getCacheStats()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'plaintext' => $plaintext
            ];
        }
    }

    /**
     * Validates URL for security
     *
     * @param string $url
     * @return bool
     */
    public static function validateUrl(string $url): bool
    {
        $allowedProtocols = ['http', 'https', 'mailto'];
        $maxLength = 2048;

        if (strlen($url) > $maxLength) {
            return false;
        }

        $parsedUrl = wp_parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme'])) {
            return false;
        }

        if (!in_array($parsedUrl['scheme'], $allowedProtocols)) {
            return false;
        }

        // Additional validation for mailto URLs
        if ($parsedUrl['scheme'] === 'mailto') {
            $email = $parsedUrl['path'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the current PBKDF2 iterations for JavaScript compatibility
     *
     * @return int
     */
    public static function getIterations(): int
    {
        return self::$iterations;
    }

    private static function setIterations(): void
    {
        $config = new Config(get_option('cryptX', []));
        self::$iterations = $config->get('iterations', self::getIterations());

    }

    /**
     * Get configuration for JavaScript
     *
     * @return array
     */
    public static function getJavaScriptConfig(): array
    {
        self::setIterations();
        return [
            'iterations' => self::getIterations(),
            'keyLength' => self::KEY_LENGTH,
            'ivLength' => self::IV_LENGTH,
            'saltLength' => self::SALT_LENGTH,
            'cipher' => self::CIPHER
        ];
    }
}