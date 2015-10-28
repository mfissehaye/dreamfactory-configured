<?php
namespace DreamFactory\Core\ADLdap\Contracts;

interface User
{
    /**
     * Gets LDAP domain name.
     *
     * @return string
     */
    public function getDomain();

    /**
     * Gets unique user ID.
     *
     * @return integer
     */
    public function getId();

    /**
     * Gets unique username.
     *
     * @return string
     */
    public function getUid();

    /**
     * Gets cn (common name).
     *
     * @return string
     */
    public function getName();

    /**
     * Gets givenname.
     *
     * @return string
     */
    public function getFirstName();

    /**
     * Gets sn (surname).
     *
     * @return string
     */
    public function getLastName();

    /**
     * Gets mail.
     *
     * @return string
     */
    public function getEmail();

    /**
     * Gets password hash.
     *
     * @return string
     */
    public function getPassword();
}