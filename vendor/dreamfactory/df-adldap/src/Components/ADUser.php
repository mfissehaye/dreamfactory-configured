<?php
namespace DreamFactory\Core\ADLdap\Components;

use DreamFactory\Library\Utility\ArrayUtils;

class ADUser extends LdapUser
{
    /**
     * {@inheritdoc}
     */
    public function getUid()
    {
        $data = $this->getData();

        return ArrayUtils::getDeep($data, 'samaccountname', 0);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return ArrayUtils::getDeep($this->getData(), 'name', 0);
    }
}