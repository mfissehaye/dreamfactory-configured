<?php
namespace DreamFactory\Core\Rackspace\Database\Seeds;

use DreamFactory\Core\Database\Seeds\BaseModelSeeder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\ServiceType;
use DreamFactory\Core\Rackspace\Components\OpenStackObjectStorageConfig;
use DreamFactory\Core\Rackspace\Components\RackspaceCloudFilesConfig;
use DreamFactory\Core\Rackspace\Services\OpenStackObjectStore;

class DatabaseSeeder extends BaseModelSeeder
{
    protected $modelClass = ServiceType::class;

    protected $records = [
        [
            'name'           => 'rackspace_cloud_files',
            'class_name'     => OpenStackObjectStore::class,
            'config_handler' => RackspaceCloudFilesConfig::class,
            'label'          => 'Rackspace Cloud Files',
            'description'    => 'File service supporting Rackspace Cloud Files Storage system.',
            'group'          => ServiceTypeGroups::FILE,
            'singleton'      => false
        ],
        [
            'name'           => 'openstack_obect_storage',
            'class_name'     => OpenStackObjectStore::class,
            'config_handler' => OpenStackObjectStorageConfig::class,
            'label'          => 'OpenStack Object Storage',
            'description'    => 'File service supporting OpenStack Object Storage system.',
            'group'          => ServiceTypeGroups::FILE,
            'singleton'      => false
        ]
    ];
}