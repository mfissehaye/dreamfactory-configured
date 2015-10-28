<?php
namespace DreamFactory\Core\ADLdap\Components;

use DreamFactory\Core\Exceptions\BadRequestException;

class ADLdap extends OpenLdap
{
    /** @var  string */
    protected $accountSuffix;

    /**
     * @param string      $host
     * @param string      $baseDn
     * @param string|null $accountSuffix
     */
    public function __construct($host, $baseDn, $accountSuffix = null)
    {
        parent::__construct($host, $baseDn);
        $this->setAccountSuffix($accountSuffix);
    }

    /**
     * @param string|null $suffix
     */
    public function setAccountSuffix($suffix = null)
    {
        if (empty($suffix)) {
            $baseDn = $this->getBaseDn();
            $suffix = static::getDomainName($baseDn);
        }

        $this->accountSuffix = $suffix;
    }

    /**
     * @return string
     */
    public function getAccountSuffix()
    {
        return $this->accountSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate($username, $password)
    {
        if (empty($username) || empty($password)) {
            throw new BadRequestException('No username and/or password provided.');
        }

        $accountSuffix = $this->getAccountSuffix();

        try {
            $preAuth = ldap_bind($this->connection, $username . '@' . $accountSuffix, $password);

            if ($preAuth) {
                $this->dn = $this->getDn($username, 'samaccountname');

                $auth = ldap_bind($this->connection, $this->dn, $password);
            } else {
                $auth = false;
            }
        } catch (\Exception $e) {
            \Log::alert('Failed to authenticate with AD server using LDAP. '.$e->getMessage());
            $auth = false;
        }

        $this->authenticated = $auth;

        return $auth;
    }

    /**
     * @return LdapUser
     */
    public function getUser()
    {
        return new ADUser($this);
    }
}