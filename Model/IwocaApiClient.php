<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\Exception\GuzzleException;
use Iwoca\Iwocapay\Api\Response\GetOrderInterface;
use Iwoca\Iwocapay\Api\Response\GetOrderInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * High-level client for iwoca API endpoints
 */
class IwocaApiClient
{
    private IwocaClientFactory $clientFactory;
    private ?IwocaClient $client = null;
    private Config $config;
    private Json $jsonSerializer;
    private GetOrderInterfaceFactory $getOrderFactory;

    /**
     * @param IwocaClientFactory $clientFactory
     * @param Config $config
     * @param Json $jsonSerializer
     * @param GetOrderInterfaceFactory $getOrderFactory
     */
    public function __construct(
        IwocaClientFactory $clientFactory,
        Config $config,
        Json $jsonSerializer,
        GetOrderInterfaceFactory $getOrderFactory
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->getOrderFactory = $getOrderFactory;
    }

    /**
     * Get the IwocaClient instance (lazy initialization, reused)
     *
     * @return IwocaClient
     */
    private function getClient(): IwocaClient
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create();
        }
        return $this->client;
    }

    /**
     * Get order from iwoca API
     *
     * @param string $orderId
     * @return GetOrderInterface
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function getOrder(string $orderId): GetOrderInterface
    {
        $rawResponse = $this->getClient()->get(
            $this->config->getApiEndpoint(
                Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                [':orderId' => $orderId]
            )
        );

        $responseJson = $rawResponse->getBody()->getContents();
        $responseData = $this->jsonSerializer->unserialize($responseJson);

        $orderResponse = $this->getOrderFactory->create();
        $orderResponse->setData($responseData['data']);

        return $orderResponse;
    }
}
