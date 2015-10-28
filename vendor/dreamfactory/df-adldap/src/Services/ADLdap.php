<?php
namespace DreamFactory\Core\ADLdap\Services;

use DreamFactory\Library\Utility\ArrayUtils;

class ADLdap extends LDAP
{
    /** Provider name */
    const PROVIDER_NAME = 'adldap';

    /**
     * Sets the Active Directory Driver.
     */
    protected function setDriver()
    {
        $host = $this->getHost();
        $baseDn = $this->getBaseDn();
        $accountSuffix = ArrayUtils::get($this->config, 'account_suffix');

        $this->driver = new \DreamFactory\Core\ADLdap\Components\ADLdap($host, $baseDn, $accountSuffix);
    }
}