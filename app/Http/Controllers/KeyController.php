<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class KeyController extends Controller
{
    /**
     * Get the HLS key with polymorphic encryption.
     *
     * @param string $keyId
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|JsonResponse|Response
     */
    public function getKey(string $clientName, string $keyId)
    {
        // 1. Fetch the original key from storage
        $originalKey = Cache::remember("video_key_{$keyId}", 60 * 60 * 24, function () use ($clientName, $keyId) {

            // This block only runs ONCE.
            $path = storage_path("app/{$clientName}/keys/{$keyId}");

            if (!file_exists($path)) return null;

            return file_get_contents($path);
        });

        if (strlen($originalKey) !== 16) {
            return response()->json(['error' => 'Invalid key length'], 500);
        }

        return response($originalKey, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Length', strlen($originalKey));

    }

    /**
     * Set session secret (for frontend coordination)
     */
    public function setSessionSecret(Request $request)
    {
        $request->validate(['secret' => 'required|string']);
        $request->session()->put('secret', $request->secret);
        return response()->json(['message' => 'Secret set']);
    }

    /**
     * Algorithm 1: XOR Encryption
     */
    private function encryptXor($data, $key)
    {
        $output = '';
        $keyLen = strlen($key);
        for ($i = 0; $i < strlen($data); $i++) {
            $output .= $data[$i] ^ $key[$i % $keyLen];
        }
        return base64_encode($output);
    }

    /**
     * Algorithm 2: Reverse String + Base64
     */
    private function encryptReverse($data)
    {
        $reversed = strrev($data);
        return base64_encode($reversed);
    }

    /**
     * Algorithm 3: AES-256-ECB with Salt
     */
    private function encryptAes($data, $sessionSecret, $salt)
    {
        // Derive a 32-byte Key from Secret + Salt (using SHA-256)
        $derivedKey = hash('sha256', $sessionSecret . $salt, true);

        // Encrypt using AES-256-ECB
        // OPENSSL_RAW_DATA returns binary
        \Log::info("PHP Derived Key: " . bin2hex($derivedKey));
        $encrypted = openssl_encrypt($data, 'aes-256-ecb', $derivedKey, OPENSSL_RAW_DATA);

        return base64_encode($encrypted);
    }
}
