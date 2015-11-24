<?php

/**
 * @property string $Domain domain name of this subsite. Can include wildcards. Do not include the URL scheme here
 * @property string $Protocol Required protocol (http or https) if only one is supported. 'automatic' implies
 * that any links to this subsite should use the current protocol, and that both are supported.
 * @property string $SubstitutedDomain Domain name with all wildcards filled in
 * @property string $FullProtocol Full protocol including ://
 * @property bool $IsPrimary Is this the primary subdomain?
 */
class SubsiteDomain extends DataObject
{
    /**
     *
     * @var array
     */
    private static $db = array(
        "Domain" => "Varchar(255)",
        "Protocol" => "Enum('http,https,automatic','automatic')",
        "IsPrimary" => "Boolean",
    );

    /**
     * Specifies that this subsite is http only
     */
    const PROTOCOL_HTTP = 'http';

    /**
     * Specifies that this subsite is https only
     */
    const PROTOCOL_HTTPS = 'https';

    /**
     * Specifies that this subsite supports both http and https
     */
    const PROTOCOL_AUTOMATIC = 'automatic';

    /**
     * Get the descriptive title for this domain
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->Domain;
    }

    /**
     *
     * @var array
     */
    private static $has_one = array(
        "Subsite" => "Subsite",
    );

    /**
     * @config
     * @var array
     */
    private static $summary_fields = array(
        'Domain',
        'IsPrimary',
    );

    /**
     * @config
     * @var array
     */
    private static $casting = array(
        'SubstitutedDomain' => 'Varchar',
        'FullProtocol' => 'Varchar',
        'AbsoluteLink' => 'Varchar',
    );

    /**
     * Whenever a Subsite Domain is written, rewrite the hostmap
     *
     * @return void
     */
    public function onAfterWrite()
    {
        Subsite::writeHostMap();
    }
    
    /**
     * 
     * @return \FieldList
     */
    public function getCMSFields()
    {
        $protocols = array(
            self::PROTOCOL_HTTP => _t('SubsiteDomain.PROTOCOL_HTTP', 'http://'),
            self::PROTOCOL_HTTPS => _t('SubsiteDomain.PROTOCOL_HTTPS', 'https://'),
            self::PROTOCOL_AUTOMATIC => _t('SubsiteDomain.PROTOCOL_AUTOMATIC', 'Automatic')
        );

        $fields = new FieldList(
            WildcardDomainField::create('Domain', $this->fieldLabel('Domain'), null, 255)
                ->setDescription(_t(
                    'SubsiteDomain.DOMAIN_DESCRIPTION',
                    'Hostname of this subsite (exclude protocol). Allows wildcards (*).'
                )),
            OptionsetField::create('Protocol', $this->fieldLabel('Protocol'), $protocols)
                ->setDescription(_t(
                    'SubsiteDomain.PROTOCOL_DESCRIPTION',
                    'When generating links to this subsite, use the selected protocol. <br />' .
                    'Selecting \'Automatic\' means subsite links will default to the current protocol.'
                )),
            CheckboxField::create('IsPrimary', $this->fieldLabel('IsPrimary'))
                ->setDescription(_t(
                    'SubsiteDomain.PROTOCOL_DESCRIPTION',
                    'Mark this as the default domain for this subsite'
                ))
        );

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * 
     * @param bool $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Domain'] = _t('SubsiteDomain.DOMAIN', 'Domain');
        $labels['Protocol'] = _t('SubsiteDomain.Protocol', 'Protocol');
        $labels['IsPrimary'] = _t('SubsiteDomain.IS_PRIMARY', 'Is Primary Domain?');

        return $labels;
    }

    /**
     * Get the link to this subsite
     *
     * @return string
     */
    public function Link()
    {
        return $this->getFullProtocol() . $this->Domain;
    }

    /**
     * Gets the full protocol (including ://) for this domain
     *
     * @return string
     */
    public function getFullProtocol()
    {
        switch ($this->Protocol) {
            case self::PROTOCOL_HTTPS:
            {
                return 'https://';
            }
            case self::PROTOCOL_HTTP:
            {
                return 'http://';
            }
            default:
            {
                return Director::protocol();
            }
        }
    }

    /**
     * Retrieves domain name with wildcards substituted with actual values
     *
     * @todo Refactor domains into separate wildcards / primary domains
     *
     * @return string
     */
    public function getSubstitutedDomain()
    {
        $currentHost = $_SERVER['HTTP_HOST'];

        // If there are wildcards in the primary domain (not recommended), make some
        // educated guesses about what to replace them with:
        $domain = preg_replace('/\.\*$/', ".{$currentHost}", $this->Domain);

        // Default to "subsite." prefix for first wildcard
        // TODO Whats the significance of "subsite" in this context?!
        $domain = preg_replace('/^\*\./', "subsite.", $domain);
        
        // *Only* removes "intermediate" subdomains, so 'subdomain.www.domain.com' becomes 'subdomain.domain.com'
        $domain = str_replace('.www.', '.', $domain);

        return $domain;
    }

    /**
     * Get absolute link for this domain
     *
     * @return string
     */
    public function getAbsoluteLink()
    {
        return $this->getFullProtocol() . $this->getSubstitutedDomain();
    }

    /**
     * Get absolute baseURL for this domain
     *
     * @return string
     */
    public function absoluteBaseURL()
    {
        return Controller::join_links(
            $this->getAbsoluteLink(),
            Director::baseURL()
        );
    }
}
