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
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect as RedirectResult;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Wirecard\ElasticEngine\Gateway\Service\TransactionServiceFactory;
use Wirecard\PaymentSdk\Response\SuccessResponse;
use Wirecard\PaymentSdk\TransactionService;

/**
 * Class Redirect
 * @package Wirecard\ElasticEngine\Controller\Frontend
 * @method Http getRequest()
 */
class Redirect extends Action implements CsrfAwareActionInterface
{
    use NoCsrfTrait;

    const CHECKOUT_URL = 'checkout/cart';

    const REDIRECT_URL = 'redirect-url';

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var TransactionServiceFactory
     */
    private $transactionServiceFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param TransactionServiceFactory $transactionServiceFactory
     * @param LoggerInterface $logger
     */
    public function __construct(Context $context, Session $checkoutSession, TransactionServiceFactory $transactionServiceFactory, LoggerInterface $logger)
    {
        $this->checkoutSession = $checkoutSession;
        $this->transactionServiceFactory = $transactionServiceFactory;
        $this->logger = $logger;
        $this->context = $context;
        parent::__construct($context);
    }


    public function execute()
    {
        $methodName = $this->getRequest()->getParam('method');
        if ($methodName == null && $this->getRequest()->isPost()) {
            $methodName = $this->getRequest()->getPost()->get('method');
        }

        if ($methodName === null || !$this->getRequest()->isPost()) {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addNoticeMessage(__('order_error'));
            $data[self::REDIRECT_URL] = $this->context->getUrl()->getRedirectUrl(self::CHECKOUT_URL);
            /** @var Json $result */
            $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $result->setData($data);

            return $result;
        }

        $this->transactionService = $this->transactionServiceFactory->create($methodName);

        $params = $this->getRequest()->getPost()->toArray();
        if (isset($params['data'])) {
            $result = $this->handleNonThreeDResponse($params['data']);
        } else {
            $result = $this->handleThreeDResponse($params);
        }

        return $result;
    }

    /**
     * Handles credit card 3D responses and returns redirect page
     *
     * @param $responseParams
     * @return RedirectResult
     * @since 1.5.2
     */
    private function handleThreeDResponse($responseParams)
    {
        /**
         * @var $resultRedirect RedirectResult
         */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $response = $this->transactionService->handleResponse($responseParams);

        if ($response instanceof SuccessResponse) {
            $this->setRedirectPath($resultRedirect, 'checkout/onepage/success');
        } else {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addNoticeMessage(__('order_error'));
            // Set redirect for error/failure case
            $this->setRedirectPath($resultRedirect, self::CHECKOUT_URL);
        }

        return $resultRedirect;
    }

    /**
     * Handles credit card non-3D responses and returns redirect url in json
     *
     * @param $responseParams
     * @return Json
     * @since 1.5.2
     */
    private function handleNonThreeDResponse($responseParams)
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $data = [
            self::REDIRECT_URL => null
        ];

        $response = $this->transactionService->processJsResponse($responseParams, $resultRedirect);

        if ($response instanceof SuccessResponse) {
            $data[self::REDIRECT_URL] = $this->context->getUrl()->getRedirectUrl('onepage/success');
        } else {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addNoticeMessage(__('order_error'));
            $data[self::REDIRECT_URL] = $this->context->getUrl()->getRedirectUrl(self::CHECKOUT_URL);
        }

        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($data);

        return $result;
    }

    /**
     * @param RedirectResult $resultRedirect
     * @param String $path
     * @return RedirectResult
     */
    private function setRedirectPath(RedirectResult $resultRedirect, $path)
    {
        return $resultRedirect->setPath($path, ['_secure' => true]);
    }
}
