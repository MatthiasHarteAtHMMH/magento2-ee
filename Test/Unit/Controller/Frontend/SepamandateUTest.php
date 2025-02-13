<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento2-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento2-ee/blob/master/LICENSE
 */

namespace Wirecard\ElasticEngine\Test\Unit\Controller\Frontend;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Wirecard\ElasticEngine\Controller\Frontend\Sepamandate;

class SepamandateUTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PageFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $resultFactory;

    /**
     * @var Page|\PHPUnit_Framework_MockObject_MockObject
     */
    private $page;

    /**
     * @var Layout|\PHPUnit_Framework_MockObject_MockObject
     */
    private $layout;

    /**
     * @var AbstractBlock|\PHPUnit_Framework_MockObject_MockObject
     */
    private $block;

    /**
     * @var $context Context|\PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    public function setUp()
    {
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->page = $this->getMockWithoutInvokingTheOriginalConstructor(Page::class);
        $this->layout = $this->getMockWithoutInvokingTheOriginalConstructor(Layout::class);
        $this->block = $this->getMockWithoutInvokingTheOriginalConstructor(AbstractBlock::class);

        $this->layout->method('getBlock')->with('frontend.sepamandate')->willReturn($this->block);
        $this->page->method('getLayout')->willReturn($this->layout);

        $this->resultFactory = $this->getMockWithoutInvokingTheOriginalConstructor(PageFactory::class);
        $this->resultFactory->method('create')->willReturn($this->page);
    }

    public function testExecute()
    {
        $prov = new Sepamandate($this->context, $this->resultFactory);
        $result = $prov->execute();

        $this->assertEquals($this->page, $result);
    }
}
