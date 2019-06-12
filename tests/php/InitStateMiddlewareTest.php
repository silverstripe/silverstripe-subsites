<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Subsites\Middleware\InitStateMiddleware;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;

class InitStateMiddlewareTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    /**
     * Original value of $_REQUEST
     *
     * @var array
     */
    protected $origServer = [];

    protected function setUp()
    {
        parent::setUp();

        $this->origServer = $_SERVER;
    }

    protected function tearDown()
    {
        $_SERVER = $this->origServer;

        parent::tearDown();
    }

    public function testDomainDetectionViaServerHeaders()
    {
        $_SERVER['HTTP_HOST'] = 'one.example.org';

        $this->getMiddleware()->process($this->getRequest(), $this->getCallback());

        $expectedSubsite = $this->objFromFixture(Subsite::class, 'domaintest1');
        $this->assertEquals($expectedSubsite->ID, $this->getState()->getSubsiteId());
    }

    public function testDomainDetectionViaRequestOverridesServerHeaders()
    {
        $_SERVER['HTTP_HOST'] = 'one.example.org';

        $this->getMiddleware()->process($this->getRequest('two.mysite.com'), $this->getCallback());

        $expectedSubsite = $this->objFromFixture(Subsite::class, 'domaintest2');
        $this->assertEquals($expectedSubsite->ID, $this->getState()->getSubsiteId());
    }

    protected function getMiddleware()
    {
        return new InitStateMiddleware();
    }

    protected function getRequest($domain = null)
    {
        $request = new HTTPRequest('GET', '/test/url');
        if ($domain) {
            $request->addHeader('host', $domain);
        }
        return $request;
    }

    protected function getCallback()
    {
        return function () {
        };
    }

    protected function getState()
    {
        return Injector::inst()->get(SubsiteState::class);
    }
}
