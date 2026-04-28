<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use Psr\Log\LoggerInterface;

class IwocaClientFactory
{

    /**
     * @var GuzzleClientFactory
     */
    private GuzzleClientFactory $guzzleClientFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Hmac
     */
    private Hmac $hmac;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param GuzzleClientFactory $guzzleClientFactory
     * @param Config $config
     * @param Hmac $hmac
     * @param LoggerInterface $logger
     */
    public function __construct(
        GuzzleClientFactory $guzzleClientFactory,
        Config $config,
        Hmac $hmac,
        LoggerInterface $logger
    ) {
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->config = $config;
        $this->hmac = $hmac;
        $this->logger = $logger;
    }

    /**
     * Create IwocaClient with automatic HMAC verification
     *
     * @return IwocaClient
     */
    public function create(): IwocaClient
    {
        $config = [
            'headers' => [
                'Cache-Control' => 'nocache',
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $this->config->getSellerAccessToken()),
                'iwocapay-integration-version' => '2.0.0'
            ],
        ];

        $guzzleClient = $this->guzzleClientFactory->create(['config' => $config]);

        return new IwocaClient(
            $guzzleClient,
            $this->hmac,
            $this->config->getSellerAccessToken(),
            $this->logger
        );
    }
}
