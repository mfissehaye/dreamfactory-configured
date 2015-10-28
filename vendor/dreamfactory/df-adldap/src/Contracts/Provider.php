<?php
namespace DreamFactory\Core\ADLdap\Contracts;

use DreamFactory\Core\ADLdap\Contracts\User as LdapUser;

interface Provider
{
    /**
     * Gets the base dn.
     *
     * @return string
     */
    public function getBaseDn();

    /**
     * Gets full dn.
     *
     * @param string $username
     * @param string $uidField
     *
     * @return string
     */
    public function getDn($username, $uidField = 'uid');

    /**
     * Authenticates User.
     *
     * @param string $username
     * @param string $password
     *
     * @return mixed
     */
    public function authenticate($username, $password);

    /**
     * Checks to see if connection is bound/authenticated.
     *
     * @return bool
     */
    public function isAuthenticated();

    /**
     * Gets user info.
     *
     * @return array
     */
    public function getUserInfo();

    /**
     * Gets the user object.
     *
     * @return LdapUser
     */
    public function getUser();

    /**
     * Gets the connection resource.
     *
     * @return resource
     */
    public function getConnection();

    /**
     * Gets the domain name.
     *
     * @param string $baseDn
     *
     * @return string
     */
    public static function getDomainName($baseDn);
}