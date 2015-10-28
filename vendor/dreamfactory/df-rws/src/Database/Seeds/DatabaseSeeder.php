<?php
namespace DreamFactory\Core\Rws\Database\Seeds;

use DreamFactory\Core\Database\Seeds\BaseModelSeeder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\ServiceType;
use DreamFactory\Core\Rws\Models\RwsConfig;
use DreamFactory\Core\Rws\Services\RemoteWeb;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = ServiceType::class;

    protected $records = [
        [
            'name'           => 'rws',
            'class_name'     => RemoteWeb::class,
            'config_handler' => RwsConfig::class,
            'label'          => 'Remote Web Service',
            'description'    => 'A service to handle Remote Web Services',
            'group'          => ServiceTypeGroups::CUSTOM,
            'singleton'      => false
        ]
    ];
}