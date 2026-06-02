<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP client wrapper that automatically verifies HMAC signatures on responses
 */
class IwocaClient
{
    private GuzzleClient $guzzleClient;
    private Hmac $hmac;
    private string $sellerAccessToken;
    private LoggerInterface $logger;

    /**
     * @param GuzzleClient $guzzleClient
     * @param Hmac $hmac
     * @param string $sellerAccessToken
     * @param LoggerInterface $logger
     */
    public function __construct(
        GuzzleClient $guzzleClient,
        Hmac $hmac,
        string $sellerAccessToken,
        LoggerInterface $logger
    ) {
        $this->guzzleClient = $guzzleClient;
        $this->hmac = $hmac;
        $this->sellerAccessToken = $sellerAccessToken;
        $this->logger = $logger;
    }

    /**
     * Perform GET request with automatic HMAC verification
     *
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        $response = $this->guzzleClient->get($uri, $options);
        $isValid = $this->verifyHmacSignature($response, $uri);
        if (!$isValid) {
            throw new LocalizedException(__('HMAC signature verification failed'));
        }

        return $response;
    }

    /**
     * Perform POST request (no HMAC verification)
     *
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->guzzleClient->post($uri, $options);
    }

    /**
     * Verify HMAC signature on response
     *
     * @param ResponseInterface $response
     * @param string $uri
     * @return bool True if HMAC is valid, false otherwise
     */
    private function verifyHmacSignature(ResponseInterface $response, string $uri): bool
    {
        // Extract HMAC header
        $hmacHeader = null;
        if ($response->hasHeader('X-Iwocapay-Hmac-Sha256')) {
            $hmacHeaders = $response->getHeader('X-Iwocapay-Hmac-Sha256');
            $hmacHeader = $hmacHeaders[0] ?? null;
            $this->logger->info(sprintf('iwocaPay HMAC header received for %s: %s', $uri, $hmacHeader));
        }

        if (!$hmacHeader) {
            $this->logger->error(sprintf('iwocaPay HMAC header missing for %s', $uri));
            return false;
        }

        // Get response body
        $responseBody = $response->getBody()->getContents();

        // Reset body stream so it can be read again by caller
        $response->getBody()->rewind();

        // Verify we have access token
        if (!$this->sellerAccessToken) {
            $this->logger->error('Seller access token not configured');
            return false;
        }

        // Compute expected HMAC
        $computedHmac = $this->hmac->computeHmac($responseBody, $this->sellerAccessToken);

        // Verify HMAC matches
        $isValid = $this->hmac->verifyHmac($hmacHeader, $computedHmac);
        if (!$isValid) {
            $this->logger->error(
                sprintf(
                    'HMAC verification failed for %s. Expected: %s, Computed: %s',
                    $uri,
                    $hmacHeader,
                    $computedHmac
                )
            );
            return false;
        }

        $this->logger->info(sprintf('HMAC verification successful for %s', $uri));
        return true;
    }
}
