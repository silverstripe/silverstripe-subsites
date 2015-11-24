<?php

/**
 * Tests {@see WildcardDomainField}
 */
class WildcardDomainFieldTest extends SapphireTest {

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider validDomains
     */
    public function testValidDomains($domain) {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider invalidDomains
     */
    public function testInvalidDomains($domain) {
        $field = new WildcardDomainField('DomainField');
        $this->assertFalse($field->checkHostname($domain), "Validate that {$domain} is an invalid domain name");
    }

    /**
     * Check that valid domains are accepted
     *
     * @dataProvider validWildcards
     */
    public function testValidWildcards($domain) {
        $field = new WildcardDomainField('DomainField');
        $this->assertTrue($field->checkHostname($domain), "Validate that {$domain} is a valid domain wildcard");
    }

    public function validDomains() {
        return array(
            array('www.mysite.com'),
            array('domain7'),
            array('mysite.co.n-z'),
            array('subdomain.my-site.com'),
            array('subdomain.mysite')
        );
    }

    public function invalidDomains() {
        return array(
            array('-mysite'),
            array('.mysite'),
            array('mys..ite'),
            array('mysite-'),
            array('mysite.'),
            array('-mysite.*'),
            array('.mysite.*'),
            array('mys..ite.*'),
            array('*.mysite-'),
            array('*.mysite.')
        );
    }
    
    public function validWildcards() {
        return array(
            array('*.mysite.com'),
            array('mys*ite.com'),
            array('*.my-site.*'),
            array('*')
        );
    }

}
