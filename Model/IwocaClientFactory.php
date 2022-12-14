<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory as GuzzleClientFactory;

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
     * @param GuzzleClientFactory $guzzleClientFactory
     * @param Config $config
     */
    public function __construct(
        GuzzleClientFactory $guzzleClientFactory,
        Config $config
    ) {
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->config = $config;
    }

    /**
     * @return GuzzleClient
     */
    public function create(): GuzzleClient
    {
        $this->config->getApiBaseUrl();

        $config = [
            'base_uri' => rtrim($this->config->getApiBaseUrl(), '/') . '/',
            'headers' => [
                'Cache-Control' => 'nocache',
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $this->config->getSellerAccessToken())
            ],
        ];

        return $this->guzzleClientFactory->create(['config' => $config]);
    }
}
