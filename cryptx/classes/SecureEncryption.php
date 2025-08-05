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
    private const ITERATIONS = 100000; // PBKDF2 iterations

    /**
     * Derives a key from password using PBKDF2 - compatible with JavaScript
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

        return hash_pbkdf2('sha256', $password, $salt, self::ITERATIONS, self::KEY_LENGTH, true);
    }

    /**
     * Encrypts plaintext using AES-256-GCM - JavaScript compatible format
     *
     * @param string $plaintext
     * @param string $password
     * @return string Base64 encoded encrypted data
     * @throws \Exception
     */
    public static function encrypt(string $plaintext, string $password): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new \Exception('OpenSSL extension not available');
        }

        if (!in_array(self::CIPHER, openssl_get_cipher_methods())) {
            throw new \Exception('AES-256-GCM cipher not available');
        }

        // Generate random salt and IV
        $salt = random_bytes(self::SALT_LENGTH);
        $iv = random_bytes(self::IV_LENGTH);

        // Derive key from password
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

        // Format: salt(16) + iv(16) + encrypted_data + tag(16)
        // This matches the JavaScript format expectation
        $combined = $salt . $iv . $encrypted . $tag;

        return base64_encode($combined);
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

            // Derive key from password
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
            throw new \Exception('Decryption failed: ' . $e->getMessage());
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
            $encrypted = self::encrypt($plaintext, $password);
            $decrypted = self::decrypt($encrypted, $password);

            return [
                'success' => true,
                'plaintext' => $plaintext,
                'encrypted' => $encrypted,
                'decrypted' => $decrypted,
                'match' => ($plaintext === $decrypted),
                'encrypted_length' => strlen($encrypted),
                'binary_length' => strlen(base64_decode($encrypted))
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

        $parsedUrl = parse_url($url);
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
}