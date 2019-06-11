<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

namespace Wirecard\ElasticEngine\Controller\Frontend;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Wirecard\ElasticEngine\Gateway\Service\TransactionServiceFactory;
use Wirecard\PaymentSdk\Response\FormInteractionResponse;
use Wirecard\PaymentSdk\Response\Response;
use Wirecard\PaymentSdk\TransactionService;

/**
 * Class Callback
 * @package Wirecard\ElasticEngine\Controller\Frontend
 * @method Http getRequest()
 */
class Callback extends Action
{
    const REDIRECT_URL = 'redirect-url';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionServiceFactory
     */
    private $transactionServiceFactory;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Callback constructor.
     * @param Context $context
     * @param Session $session
     * @param LoggerInterface $logger
     * @param TransactionServiceFactory $transactionServiceFactory
     */
    public function __construct(
        Context $context,
        Session $session,
        LoggerInterface $logger,
        TransactionServiceFactory $transactionServiceFactory
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->baseUrl = $context->getUrl()->getRouteUrl('wirecard_elasticengine');
        $this->logger = $logger;
        $this->transactionServiceFactory = $transactionServiceFactory;
        $this->urlBuilder = $context->getUrl();
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $response = null;
        $data = null;
        if ($this->getRequest()->getParam('jsresponse')) {
            $response = $this->getRequest()->getPost()->toArray();
            $data = $this->handleThreeDTransactions($response);
        } else {
            $data = [
                self::REDIRECT_URL => null,
                'form-url' => null,
                'form-method' => null,
                'form-fields' => null
            ];

            if ($this->session->hasRedirectUrl()) {
                $data[self::REDIRECT_URL] = $this->session->getRedirectUrl();
                $this->session->unsRedirectUrl();
            } elseif ($this->session->hasFormUrl()) {
                $data['form-url'] = $this->session->getFormUrl();
                $data['form-method'] = $this->session->getFormMethod();
                $data['form-fields'] = $this->session->getFormFields();

                $this->session->unsFormUrl();
                $this->session->unsFormMethod();
                $this->session->unsFormFields();
            } else {
                $data[self::REDIRECT_URL] = $this->baseUrl . 'frontend/redirect';
            }
        }
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData([
            'status' => 'OK',
            'data' => $data,
        ]);

        return $result;
    }

    /**
     * Handle callback with acs url - jsresponse
     *
     * @param $response
     * @return mixed
     */
    private function handleThreeDTransactions($response)
    {
        $methodName = 'creditcard';

        try {
            /** @var TransactionService $transactionService */
            $transactionService = $this->transactionServiceFactory->create($methodName);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        // Create credit card redirection url
        $wdBaseUrl    = $this->urlBuilder->getRouteUrl('wirecard_elasticengine');
        $methodAppend = '?method=' . urlencode($methodName);
        $url = $wdBaseUrl . 'frontend/redirect' . $methodAppend;

        /** @var Response $response */
        $response = $transactionService->processJsResponse($response['jsresponse'], $url);
        $data[self::REDIRECT_URL] = $this->baseUrl . 'frontend/redirect';

        if ($response instanceof FormInteractionResponse) {
            unset($data[self::REDIRECT_URL]);
            $data['form-url'] = html_entity_decode($response->getUrl());
            $data['form-method'] = $response->getMethod();
            foreach ($response->getFormFields() as $key => $value) {
                $data[$key] = html_entity_decode($value);
            }
        }

        return $data;
    }
}
