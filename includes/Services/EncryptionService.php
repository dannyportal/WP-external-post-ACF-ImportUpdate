<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

Class EncryptionService {
    public static string $default_cipher='AES-256-CBC';
    
    /**
     * Encrypts a value using symmetric encryption via the openssl library.
     * 
     * @param string $raw_val Origanl value to be encrypted
     * @param string $cipher Optional: default is AES-256-CBC. Use openssl compatible cipher. See http://php.net/manual/en/function.openssl-get-cipher-methods.php
     * @return string Encrypted base-64 data in the format of $iv . ':' . $encrypted_data
     */
    public static function encrypt_reversible(string $raw_val, ?string $cipher=null): string {
        isset($cipher) || $cipher=EncryptionService::$default_cipher;
        
        // generate Initialization Vector of appropriate length for the default cipher
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        
        // If we lose the $iv variable, we can't decrypt this, so concatenate it
        // with the encrypted data using a separator that we know won't exist in 
        // base64-encoded data (in this case, ':')
        return base64_encode($iv) . ':' . base64_encode(
            openssl_encrypt(
                $raw_val, 
                $cipher, 
                EncryptionService::get_key(), 
                OPENSSL_RAW_DATA, 
                $iv
            )
        );
    }
    
    /**
     * Decrypts a symmetrically encrypted value via the openssl library.
     * 
     * @param string $encrypted_val base64-encoded string in the format of $iv . ':' . $encrypted_data
     * @param string $cipher Optional: default is AES-256-CBC. Use openssl compatible cipher. See http://php.net/manual/en/function.openssl-get-cipher-methods.php
     * @return string String containing decrypted data, or FALSE if no value was found.
     */
    public static function decrypt_reversible(string $encrypted_val, ?string $cipher=null): string {
        isset($cipher) || $cipher=EncryptionService::$default_cipher;

        // To decrypt, separate the encrypted data from the initialization 
        // vector ($iv).  Assume $iv is first, using ':' as delimiter
        $parts = explode(':', $encrypted_val);
        
        return ($parts && count($parts) > 1) ? openssl_decrypt(
            base64_decode($parts[1]), // encrypted data
            $cipher, 
            EncryptionService::get_key(), 
            OPENSSL_RAW_DATA, 
            base64_decode($parts[0]) // initialization vector
        ) : false;
    }
    
    /**
     * Compares an unencrypted value with a reversibly encrypted value.
     * 
     * @param string $raw_val String that needs to be compared against the encrypted value.
     * @param string $encrypted_val base64-encoded encrypted data, in the format of $iv . ':' . $encrypted_data
     * @return bool Returns True if values match, False if they do not match
     */
    public static function compare_reversible(string $raw_val, string $encrypted_val, ?string $cipher=null): bool {
        return ($raw_val === EncryptionService::decrypt_reversible($encrypted_val, $cipher));
    }
    
    /**
     * Retrieves the encryption key defined in the WordPress configuration.
     * 
     * @return string The encryption key to use for encrypting/decrypting data.
     */
    private static function get_key(): string {
        // Use the built-in WordPress auth key for the site
        return SECURE_AUTH_KEY;
    }

    /**
     * Generates a random cryptographically secure token string consisting of only alphanumerics
     * of a specified length.
     *
     * @param int $length Desired length of the random token string
     * @return string Generated random token string.
     */
    public static function generate_token(int $length): string {
        $randomBytes=openssl_random_pseudo_bytes($length + 2);
        
        $str = strtr(
            substr(
                base64_encode($randomBytes), 0 ,$length
            ),
            array('+'=>'_','/'=>'~')
        );
        
        return str_replace(array('~', '_'), array('a', 'z'), $str);
    }
}