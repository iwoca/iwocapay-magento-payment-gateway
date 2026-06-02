<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

class Hmac
{
    /**
     * Compute HMAC-SHA256 signature.
     *
     * @param string $data The data to hash (raw JSON response body)
     * @param string $hashKey The secret key (seller access token)
     * @return string Base64-encoded HMAC signature
     */
    public function computeHmac(string $data, string $hashKey): string
    {
        // hash_hmac with raw_output=true returns binary data (like Python's digest())
        $digest = hash_hmac('sha256', $data, $hashKey, true);

        // base64_encode converts to the same format as Python
        return base64_encode($digest);
    }

    /**
     * Verify HMAC signature using timing-safe comparison
     *
     * @param string $expectedHmac The HMAC from the response header
     * @param string $computedHmac The HMAC we computed from the body
     * @return bool True if HMACs match, false otherwise
     */
    public function verifyHmac(string $expectedHmac, string $computedHmac): bool
    {
        // Use hash_equals for timing-safe comparison to prevent timing attacks
        return hash_equals($expectedHmac, $computedHmac);
    }
}
