<?php

/**
 * A text field that accepts only valid domain names, but allows the wildcard (*) character
 */
class WildcardDomainField extends TextField
{
    /**
     * Validate this field as a valid hostname
     *
     * @param Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        if ($this->checkHostname($this->Value())) {
            return true;
        }

        $validator->validationError(
            $this->getName(),
            _t("DomainNameField.INVALID_DOMAIN", "Invalid domain name"),
            "validation"
        );
        return false;
    }

    /**
     * Check if the given hostname is valid.
     *
     * @param string $hostname
     * @return bool True if this hostname is valid
     */
    public function checkHostname($hostname)
    {
        return (bool)preg_match('/^([a-z0-9\*]+[\-\.])*([a-z0-9\*]+)$/', $hostname);
    }

    public function Type()
    {
        return 'text wildcarddomain';
    }
}
