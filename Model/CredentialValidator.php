<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Verifies a seller's iwocaPay credentials against the connection_check
 * endpoint.
 *
 * The endpoint returns 200 only when the access token is valid AND belongs to
 * the given seller in that environment. This catches a seller who has entered
 * the wrong token, the wrong Seller ID, or credentials for the wrong
 * environment entirely (staging and production use different credentials).
 *
 * Mirrors connection-check.php in the WooCommerce plugin: the check is a plain
 * status-code check (no HMAC verification), and any failure - bad credentials
 * OR a transient/unreachable API - is reported as an error so the caller can
 * refuse to persist an unverified key.
 */
class CredentialValidator
{
    private const INTEGRATION_NAME = 'magento';

    private GuzzleClientFactory $guzzleClientFactory;
    private Config $config;
    private LoggerInterface $logger;
    private Version $version;

    public function __construct(
        GuzzleClientFactory $guzzleClientFactory,
        Config $config,
        LoggerInterface $logger,
        Version $version
    ) {
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->version = $version;
    }

    /**
     * Verify the given credentials and return a single seller-facing verdict.
     *
     * @param string $sellerId
     * @param string $accessToken
     * @param int $mode One of Mode::STAGING_MODE / Mode::PROD_MODE.
     * @return array{type: string, text: string} 'success' or 'error' + message.
     */
    public function evaluate(string $sellerId, string $accessToken, int $mode): array
    {
        $sellerId = trim($sellerId);
        $accessToken = trim($accessToken);

        if ('' === $sellerId || '' === $accessToken) {
            return [
                'type' => 'error',
                'text' => (string)__('Please enter both a Seller ID and Seller Access Token.'),
            ];
        }

        $code = $this->connectionCheckStatusCode($sellerId, $accessToken, $mode);

        if (200 === $code) {
            return [
                'type' => 'success',
                'text' => (string)__('Your iwocaPay credentials were verified successfully.'),
            ];
        }

        // 401/403/404 mean the credentials simply aren't valid for this
        // environment (wrong token, wrong seller, or wrong environment).
        if (in_array($code, [401, 403, 404], true)) {
            return [
                'type' => 'error',
                'text' => (string)__(
                    'We couldn\'t verify your iwocaPay credentials. Make sure your Seller Access Token and '
                    . 'Seller ID are correct for the selected Mode - staging and production use different '
                    . 'credentials, so credentials for the other environment won\'t work here.'
                ),
            ];
        }

        // Any other status, or an unreachable API, is treated as a transient
        // problem rather than a credential error. The save is still blocked so
        // an unverified key is never persisted.
        return [
            'type' => 'error',
            'text' => (string)__(
                'We couldn\'t verify your iwocaPay credentials right now%1. Your settings were not saved - '
                . 'please try again.',
                null === $code ? '' : sprintf(' (error %d)', $code)
            ),
        ];
    }

    /**
     * Call connection_check for the given credentials and return the HTTP
     * status code, or null if the request could not be completed.
     *
     * @param string $sellerId
     * @param string $accessToken
     * @param int $mode
     * @return int|null
     */
    private function connectionCheckStatusCode(string $sellerId, string $accessToken, int $mode): ?int
    {
        $url = $this->config->getConnectionCheckUrl($sellerId, $mode)
            . '?integration_name=' . self::INTEGRATION_NAME
            . '&integration_version=' . rawurlencode($this->version->get());

        $client = $this->guzzleClientFactory->create(['config' => [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'iwocapay-integration-version' => $this->version->get(),
            ],
            RequestOptions::TIMEOUT => 20,
            RequestOptions::CONNECT_TIMEOUT => 5,
            // We only care about the status code; don't throw on 4xx/5xx.
            RequestOptions::HTTP_ERRORS => false,
        ]]);

        try {
            $response = $client->get($url);
            return $response->getStatusCode();
        } catch (BadResponseException $e) {
            // With http_errors disabled this is unlikely, but handle it anyway.
            return $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
        } catch (GuzzleException $e) {
            $this->logger->error('iwocaPay connection_check failed: ' . $e->getMessage());
            return null;
        }
    }
}
