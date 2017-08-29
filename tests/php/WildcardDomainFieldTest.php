<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\Forms\WildcardDomainField;

/**
 * Tests {@see WildcardDomainField}
 */
class WildcardDomainFieldTest extends SapphireTest
{

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider validDomains
     * @param $domain
     */
    public function testValidDomains($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider invalidDomains
     * @param $domain
     */
    public function testInvalidDomains($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertFalse($field->checkHostname($domain), "Validate that {$domain} is an invalid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider validWildcards
     * @param $domain
     */
    public function testValidWildcards($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain wildcard");
    }

    public function validDomains()
    {
        return [
            ['www.mysite.com'],
            ['domain7'],
            ['mysite.co.n-z'],
            ['subdomain.my-site.com'],
            ['subdomain.mysite']
        ];
    }

    public function invalidDomains()
    {
        return [
            ['-mysite'],
            ['.mysite'],
            ['mys..ite'],
            ['mysite-'],
            ['mysite.'],
            ['-mysite.*'],
            ['.mysite.*'],
            ['mys..ite.*'],
            ['*.mysite-'],
            ['*.mysite.']
        ];
    }

    public function validWildcards()
    {
        return [
            ['*.mysite.com'],
            ['mys*ite.com'],
            ['*.my-site.*'],
            ['*']
        ];
    }
}
