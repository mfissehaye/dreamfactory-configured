<?php
namespace DreamFactory\Core\ADLdap\Components;

use DreamFactory\Core\ADLdap\Contracts\Provider;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;

class OpenLdap implements Provider
{
    /** @var resource */
    protected $connection;

    /** @var  string */
    protected $baseDn;

    /** @var  string */
    protected $dn;

    /** @var array */
    protected $userData = [];

    /** @var bool */
    protected $authenticated = false;

    /**
     * @param $host
     * @param $baseDn
     */
    public function __construct($host, $baseDn)
    {
        $connection = ldap_connect($host);
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        $this->connection = $connection;
        $this->baseDn = $baseDn;
    }

    /**
     * @return string
     */
    public function getBaseDn()
    {
        return $this->baseDn;
    }

    /**
     * Performs user authentication.
     *
     * @param $username
     * @param $password
     *
     * @return bool
     * @throws BadRequestException
     */
    public function authenticate($username, $password)
    {
        if (empty($username) || empty($password)) {
            throw new BadRequestException('No username and/or password provided.');
        }

        $this->dn = $this->getDn($username);

        try {
            $auth = ldap_bind($this->connection, $this->dn, $password);
        } catch (\Exception $e) {
            \Log::alert('Failed to authenticate using LDAP. '.$e->getMessage());
            $auth = false;
        }

        $this->authenticated = $auth;

        return $auth;
    }

    /**
     * Checks to see if the instance is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * Reads all objects.
     *
     * @return array
     */
    public function getUserInfo()
    {
        if ($this->authenticated) {
            if (empty($this->userData)) {
                $rs = ldap_read($this->connection, $this->dn, "(objectclass=*)");
                $this->userData = ldap_get_entries($this->connection, $rs);
            }

            return $this->userData;
        }

        return [];
    }

    /**
     * @return LdapUser
     */
    public function getUser()
    {
        return new LdapUser($this);
    }

    /**
     * Returns connection resource.
     *
     * @return resource
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Fetches domain name from base DN.
     *
     * @param $baseDn
     *
     * @return string
     */
    public static function getDomainName($baseDn)
    {
        $baseDn = str_replace('DC=', 'dc=', $baseDn);
        $baseDn = substr($baseDn, strpos($baseDn, 'dc='));
        list($dc1, $dc2) = explode(',', $baseDn);

        $dc1 = substr($dc1, strpos($dc1, '=') + 1);
        $dc2 = substr($dc2, strpos($dc2, '=') + 1);

        $domain = $dc1 . '.' . $dc2;

        return $domain;
    }

    /**
     * Gets DN by username
     *
     * @param $username
     * @param $uidField
     *
     * @return string
     */
    public function getDn($username, $uidField = 'uid')
    {
        $baseDn = $this->baseDn;
        $connection = $this->connection;

        $search = ldap_search($connection, $baseDn, '(' . $uidField . '=' . $username . ')');
        $result = ldap_get_entries($connection, $search);

        $dn = ArrayUtils::getDeep($result, 0, 'dn');

        return $dn;
    }
}