<?php
namespace DreamFactory\Core\ADLdap\Database\Seeds;

use DreamFactory\Core\ADLdap\Models\LDAPConfig;
use DreamFactory\Core\ADLdap\Services\ADLdap;
use DreamFactory\Core\ADLdap\Services\LDAP;
use DreamFactory\Core\Database\Seeds\BaseModelSeeder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\ServiceType;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = ServiceType::class;

    protected $records = [
        [
            'name'           => 'adldap',
            'class_name'     => ADLdap::class,
            'config_handler' => LDAPConfig::class,
            'label'          => 'Active Directory LDAP',
            'description'    => 'A service for supporting Active Directory integration',
            'group'          => ServiceTypeGroups::LDAP,
            'singleton'      => false
        ],
        [
            'name'           => 'ldap',
            'class_name'     => LDAP::class,
            'config_handler' => LDAPConfig::class,
            'label'          => 'Standard LDAP',
            'description'    => 'A service for supporting Open LDAP integration',
            'group'          => ServiceTypeGroups::LDAP,
            'singleton'      => false
        ]
    ];
}