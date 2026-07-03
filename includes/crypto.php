<?php
/**
 * ProxyCrypto — AES-256-CBC Encryption for Proxy QR Privacy Guard
 * Compliance: RA 10173 (Data Privacy Act of 2012)
 * 
 * Uses OpenSSL AES-256-CBC with a random IV per encryption call.
 * The key is derived from a master passphrase using SHA-256.
 */
class ProxyCrypto {
    private static $passphrase = "C@r3l1nk_Pr0xyS3cur3_P@ss2026!#RA10173";
    private static $cipher     = "AES-256-CBC";

    /**
     * Encrypt an array payload and return a URL-safe base64 string.
     * Format: base64(iv_hex + ":" + ciphertext_base64)
     */
    public static function encrypt($data) {
        $key       = hash('sha256', self::$passphrase, true); // 32-byte binary key
        $ivLength  = openssl_cipher_iv_length(self::$cipher);
        $iv        = openssl_random_pseudo_bytes($ivLength);
        $json      = json_encode($data);
        $encrypted = openssl_encrypt($json, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return null;
        }

        // Combine IV and ciphertext, encode to URL-safe base64
        $combined = base64_encode($iv) . ':' . base64_encode($encrypted);
        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    /**
     * Decrypt a URL-safe base64 token back to an array.
     * Returns null on any failure (tampered, expired, or corrupt data).
     */
    public static function decrypt($token) {
        try {
            // Restore standard base64
            $token    = base64_decode(strtr($token, '-_', '+/') . str_repeat('=', (4 - strlen($token) % 4) % 4));
            if ($token === false) return null;

            $parts = explode(':', $token, 2);
            if (count($parts) !== 2) return null;

            $iv        = base64_decode($parts[0]);
            $encrypted = base64_decode($parts[1]);
            if ($iv === false || $encrypted === false) return null;

            $key       = hash('sha256', self::$passphrase, true);
            $decrypted = openssl_decrypt($encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) return null;

            return json_decode($decrypted, true);
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
