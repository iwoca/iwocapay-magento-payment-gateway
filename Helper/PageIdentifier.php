<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;

enum PageType: string
{
    case CHECKOUT = 'checkout';
    case BASKET = 'basket';
    case EXTERNAL_REDIRECT = 'external_redirect';
    case INTERNAL_REDIRECT = 'internal_redirect';
    case NOT_A_PAGE = 'not_a_page';
    case UNKNOWN = 'unknown';
}

class PageIdentifier extends AbstractHelper
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param RequestInterface $request
     */
    public function __construct(Context $context, RequestInterface $request, ResponseInterface $response, StoreManagerInterface $storeManager)
    {
        parent::__construct($context);
        $this->request = $request;
        $this->response = $response;
        $this->storeManager = $storeManager;
    }

    public function isLandingPage(): bool
    {
        return !$this->request->getServer('HTTP_REFERER');
    }

    public function getType(): PageType
    {
        $isAjax = $this->request->isXmlHttpRequest();
        $isGet = $this->request->isGet();

        if ($isAjax || !$isGet) {
            return PageType::NOT_A_PAGE;
        }

        $redirectUrl = strval($this->response->getHeader('Location'));
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        if ($redirectUrl) {
            if (strpos($redirectUrl, $baseUrl) === false) {
                return PageType::EXTERNAL_REDIRECT;
            }
            return PageType::INTERNAL_REDIRECT;
        }

        if ($this->isCheckoutPage()) {
            return PageType::CHECKOUT;
        }

        if ($this->isBasketPage()) {
            return PageType::BASKET;
        }

        return PageType::UNKNOWN;
    }

    /**
     * Check if the current page is a checkout page
     *
     * @return bool
     */
    public function isCheckoutPage(): bool
    {
        $routeName = $this->request->getRouteName();
        $controllerName = $this->request->getControllerName();
        $actionName = $this->request->getActionName();

        // Check for common checkout pages
        if ($routeName === 'checkout') {
            return true;
        }

        // Additional checks for specific checkout steps
        if ($routeName === 'onestepcheckout' || $routeName === 'multistepcheckout') {
            return true;
        }

        if ($routeName === 'firecheckout' || $routeName === 'amasty_checkout') {
            return true;
        }

        // Add more custom checks if needed
        return false;
    }

    /**
     * Check if the current page is a basket (cart) page
     *
     * @return bool
     */
    public function isBasketPage(): bool
    {
        $routeName = $this->request->getRouteName();
        $controllerName = $this->request->getControllerName();
        $actionName = $this->request->getActionName();

        // Check for common basket (cart) pages
        if ($routeName === 'checkout' && $controllerName === 'cart') {
            return true;
        }

        // Additional checks for specific cart actions
        if ($routeName === 'checkout' && in_array($controllerName, ['cart', 'minicart'])) {
            return true;
        }

        if ($routeName === 'sales' && $controllerName === 'quote') {
            return true;
        }

        // Add more custom checks if needed
        return false;
    }
}
