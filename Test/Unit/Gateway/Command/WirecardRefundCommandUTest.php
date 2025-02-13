<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento2-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento2-ee/blob/master/LICENSE
 */

namespace Wirecard\ElasticEngine\Test\Unit\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Psr\Log\LoggerInterface;
use Wirecard\ElasticEngine\Gateway\Command\WirecardRefundCommand;
use Wirecard\ElasticEngine\Gateway\Request\TransactionFactory;
use Wirecard\ElasticEngine\Gateway\Service\TransactionServiceFactory;
use Wirecard\ElasticEngine\Model\Adminhtml\Source\PaymentAction;
use Wirecard\PaymentSdk\Entity\Status;
use Wirecard\PaymentSdk\Entity\StatusCollection;
use Wirecard\PaymentSdk\Response\FailureResponse;
use Wirecard\PaymentSdk\Response\Response;
use Wirecard\PaymentSdk\Response\SuccessResponse;
use Wirecard\PaymentSdk\Transaction\Operation;
use Wirecard\PaymentSdk\Transaction\PayPalTransaction;
use Wirecard\PaymentSdk\Transaction\Transaction;
use Wirecard\PaymentSdk\TransactionService;

class WirecardRefundCommandUTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var TransactionServiceFactory
     */
    private $transactionServiceFactory;

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigInterface
     */
    private $methodConfig;

    /**
     * @var array
     */
    private $commandSubject;

    public function setUp()
    {
        // Transaction mocks
        $this->response = $this->getMockBuilder(SuccessResponse::class)
            ->disableOriginalConstructor()->getMock();

        $this->transactionFactory = $this->getMockBuilder(TransactionFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->transactionFactory->method('refund')
            ->willReturn($this->getMock(PayPalTransaction::class));

        // TransactionService mocks
        $this->transactionService = $this->getMockBuilder(TransactionService::class)
            ->disableOriginalConstructor()->getMock();
        $this->transactionService->method('process')->willReturn($this->response);

        $this->transactionServiceFactory = $this->getMockBuilder(TransactionServiceFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->transactionServiceFactory->method('create')->willReturn($this->transactionService);

        $this->logger = $this->getMock(LoggerInterface::class);

        $this->handler = $this->getMock(HandlerInterface::class);

        $this->methodConfig = $this->getMock(ConfigInterface::class);
        $this->methodConfig->method('getValue')->willReturn('authorize');

        $stateObject = $this->getMock(DataObject::class);
        $this->commandSubject = ['stateObject' => $stateObject];
    }

    public function testExecuteRefundTransactionService()
    {
        $testTransactionServiceFactory = $this->getMockBuilder(TransactionServiceFactory::class)
            ->disableOriginalConstructor()->getMock();
        $testTransactionServiceFactory->method('create')->willReturn($this->transactionService);

        // Test if the transactionService is created with the correct values
        $testTransactionServiceFactory->expects($this->once())->method('create');

        /** @var TransactionServiceFactory $testTransactionServiceFactory */
        $command = new WirecardRefundCommand(
            $this->transactionFactory,
            $testTransactionServiceFactory,
            $this->logger,
            $this->handler,
            $this->methodConfig
        );

        $command->execute($this->commandSubject);
    }

    /**
     * @dataProvider transactionDataProvider
     * @param $transactionClass
     */
    public function testExecuteLogsException($transactionClass)
    {
        // Transaction mocks
        $transactionFactoryMock = $this->getMockBuilder(TransactionFactory::class)
            ->disableOriginalConstructor()->getMock();
        $transactionFactoryMock->method('refund')->willReturn($this->getMock($transactionClass));

        $exception = new \Exception('Testing the exception');

        // TransactionService mocks
        $transactionServiceMock = $this->getMockBuilder(TransactionService::class)
            ->disableOriginalConstructor()->getMock();
        $transactionServiceMock->method('process')->willThrowException($exception);

        $transactionServiceFactoryMock = $this->getMockBuilder(TransactionServiceFactory::class)
            ->disableOriginalConstructor()->getMock();
        $transactionServiceFactoryMock->method('create')->willReturn($transactionServiceMock);

        // Test if the logger gets the exception message
        $loggerMock = $this->getMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error')->with($this->equalTo($exception->getMessage()));

        /**
         * @var TransactionFactory $transactionFactoryMock
         * @var TransactionServiceFactory $transactionServiceFactoryMock
         */
        $command = new WirecardRefundCommand(
            $transactionFactoryMock,
            $transactionServiceFactoryMock,
            $loggerMock,
            $this->handler,
            $this->methodConfig
        );

        $command->execute($this->commandSubject);
    }

    public function transactionDataProvider()
    {
        return [
            [Transaction::class, PaymentAction::AUTHORIZE, Operation::PAY],
            [Transaction::class, PaymentAction::AUTHORIZE_CAPTURE, Operation::PAY],
            [PayPalTransaction::class, PaymentAction::AUTHORIZE, Operation::RESERVE],
            [PayPalTransaction::class, PaymentAction::AUTHORIZE_CAPTURE, Operation::PAY]
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExecuteThrows()
    {
        // TransactionService mocks
        $transactionServiceMock = $this->getMockBuilder(TransactionService::class)
            ->disableOriginalConstructor()->getMock();

        $status = $this->getMockBuilder(Status::class)->disableOriginalConstructor()->getMock();
        $status->method('getDescription')->willReturn('description');

        $statusCollection = $this->getMockBuilder(StatusCollection::class)->disableArgumentCloning()->getMock();
        $statusCollection->method('getIterator')->willReturn([$status]);

        $failureResponse = $this->getMockBuilder(FailureResponse::class)->disableOriginalConstructor()->getMock();
        $failureResponse->method('getStatusCollection')->willReturn($statusCollection);

        $transactionServiceMock->method('process')->willReturn($failureResponse);

        $transactionServiceFactoryMock = $this->getMockBuilder(TransactionServiceFactory::class)
            ->disableOriginalConstructor()->getMock();
        $transactionServiceFactoryMock->method('create')->willReturn($transactionServiceMock);

        $command = new WirecardRefundCommand(
            $this->transactionFactory,
            $transactionServiceFactoryMock,
            $this->logger,
            $this->handler,
            $this->methodConfig
        );

        $stateObject = $this->getMock(DataObject::class);
        $commandSubject = ['stateObject' => $stateObject];

        $command->execute($commandSubject);
    }
}
