<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Subsites\Forms\WildcardDomainField;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests {@see WildcardDomainField}
 */
class WildcardDomainFieldTest extends SapphireTest
{

    /**
     * Check that valid domains are accepted
     *
     * @param $domain
     */
    #[DataProvider('validDomains')]
    public function testValidDomains($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @param $domain
     */
    #[DataProvider('invalidDomains')]
    public function testInvalidDomains($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertFalse($field->checkHostname($domain), "Validate that {$domain} is an invalid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @param $domain
     */
    #[DataProvider('validWildcards')]
    public function testValidWildcards($domain)
    {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain wildcard");
    }

    public static function validDomains()
    {
        return [
            ['www.mysite.com'],
            ['domain7'],
            ['mysite.co.n-z'],
            ['subdomain.my-site.com'],
            ['subdomain.mysite'],
            ['subdomain.mysite.com:80'],
            ['mysite:80']
        ];
    }

    public static function invalidDomains()
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
            ['*.mysite.'],
            [':1234']
        ];
    }

    public static function validWildcards()
    {
        return [
            ['*.mysite.com'],
            ['mys*ite.com'],
            ['*.my-site.*'],
            ['*']
        ];
    }
}
