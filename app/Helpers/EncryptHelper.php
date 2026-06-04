<?php

use Illuminate\Support\Facades\Crypt;

if (!function_exists('encryptId')) {
    function encryptId($id)
    {
        if (empty($id) || $id === 'all') {
            return $id;
        }
        try {
            return Crypt::encryptString((string)$id);
        } catch (\Exception $e) {
            return $id;
        }
    }
}

if (!function_exists('decryptId')) {
    function decryptId($value)
    {
        if (empty($value) || $value === 'all') {
            return $value;
        }
        if (is_numeric($value)) {
            return $value;
        }
        try {
            $decrypted = Crypt::decryptString($value);
            return is_numeric($decrypted) ? (int)$decrypted : $decrypted;
        } catch (\Exception $e) {
            return $value;
        }
    }
}