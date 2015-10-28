<?php
namespace DreamFactory\Core\Salesforce\Database\Seeds;

use DreamFactory\Core\Database\Seeds\BaseModelSeeder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\ServiceType;
use DreamFactory\Core\Salesforce\Models\SalesforceConfig;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = ServiceType::class;

    protected $records = [
        [
            'name'           => 'salesforce_db',
            'class_name'     => SalesforceDb::class,
            'config_handler' => SalesforceConfig::class,
            'label'          => 'SalesforceDB',
            'description'    => 'Database service for Salesforce connections.',
            'group'          => ServiceTypeGroups::DATABASE,
            'singleton'      => false,
        ]
    ];
}
