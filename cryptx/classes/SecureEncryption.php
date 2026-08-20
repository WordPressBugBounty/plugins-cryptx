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

    /**
     * Performance optimization: one salt per request.
     *
     * The salt is generated once per page load and reused for every encryption of that
     * request, so the PBKDF2 key derivation runs once instead of once per address.
     * This is safe: AES-GCM requires unique IVs, not unique salts - and the IV is still
     * generated freshly for every single encrypt() call (see encrypt()).
     */
    private static ?string $requestSalt = null;

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
     * Returns the salt for the current request, generating it on first use.
     *
     * Reusing the salt within one request is what makes the key cache effective:
     * all addresses of a page share the same password anyway, so they may share the
     * derived key. Uniqueness of the ciphertext is provided by the per-encryption IV.
     *
     * @return string
     * @throws \Exception
     */
    private static function getRequestSalt(): string
    {
        if (self::$requestSalt === null) {
            self::$requestSalt = random_bytes(self::SALT_LENGTH);
        }
        return self::$requestSalt;
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

        // Performance optimization: Cache derived keys.
        // The iteration count is part of the cache key: setIterations() may change it
        // within a single request, and the same password+salt yields a different key
        // for a different iteration count.
        $cacheKey = hash('sha256', self::getIterations() . '|' . $password . $salt);

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

        // Performance optimization: the salt is generated once per request, which lets
        // the key cache do its job (one PBKDF2 run per page instead of one per address).
        $salt = self::getRequestSalt();

        // SECURITY: the IV must NEVER be cached or reused. Reusing an IV with the same
        // key breaks AES-GCM completely (keystream reuse, forgeable auth tag).
        // Therefore random_bytes() runs on every single encrypt() call.
        $iv = random_bytes(self::IV_LENGTH);

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
     * Also drops the remembered request salt, so the next encrypt() starts from a
     * freshly generated salt and a genuinely empty cache.
     *
     * @return void
     */
    public static function clearKeyCache(): void
    {
        self::$keyCache = [];
        self::$requestSalt = null;
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

        // Without this the iteration count is whatever a previous encrypt()
        // happened to leave behind -- or the built-in default, if this request
        // only ever decrypts. With a configured count other than 10000 the key
        // derivation would then silently produce the wrong key.
        self::setIterations();

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

    // debugEncryption() and getCacheStats() used to sit here. The second was
    // only ever called by the first, so removing one left the other behind --
    // which is how dead code usually spreads.
    //
    // debugEncryption(): it encrypted a string, decrypted it
    // again and returned both, plus timings and the key-cache statistics. It
    // had no caller anywhere in the plugin and was shipped to every site all
    // the same. Nothing was reachable through it -- it is a static method, not
    // an endpoint -- but a method that hands back a plaintext next to its
    // ciphertext is a poor thing to leave lying around for the next person who
    // needs somewhere to hook a quick diagnosis.

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

    /** Bounds for the PBKDF2 iteration count taken from the stored option. */
    private const MIN_ITERATIONS = 1000;
    private const MAX_ITERATIONS = 1000000;

    private static function setIterations(): void
    {
        $config = new Config(get_option('cryptX', []));
        $configured = $config->get('iterations', self::$iterations);

        // The option is not necessarily a sane integer: the settings page keeps
        // it as a string, and a hand-edited row can hold anything. A zero makes
        // hash_pbkdf2() throw a ValueError and a non-numeric string a TypeError
        // -- neither of which is an \Exception, so the fallback in
        // CryptX::encryptEmailAddressSecure() would not catch them and the
        // front end would fatal on every page carrying an address.
        if (!is_numeric($configured)) {
            return;
        }

        self::$iterations = max(self::MIN_ITERATIONS, min(self::MAX_ITERATIONS, (int) $configured));
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