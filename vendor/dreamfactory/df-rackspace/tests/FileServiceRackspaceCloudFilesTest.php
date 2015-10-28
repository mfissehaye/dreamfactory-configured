<?php
use DreamFactory\Library\Utility\Enums\Verbs;

class FileServiceRackspaceCloudFilesTest extends \DreamFactory\Core\Testing\FileServiceTestCase
{
    protected static $staged = false;

    protected $serviceId = 'ros';

    public function stage()
    {
        parent::stage();

        Artisan::call('migrate', ['--path' => 'vendor/dreamfactory/df-rackspace/database/migrations/']);
        Artisan::call('db:seed', ['--class' => DreamFactory\Core\Rackspace\Database\Seeds\DatabaseSeeder::class]);
        if (!$this->serviceExists('ros')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"        => "ros",
                    "label"       => "Rackspace Cloud Files service",
                    "description" => "Rackspace Cloud Files service for unit test",
                    "is_active"   => true,
                    "type"        => "rackspace_cloud_files",
                    "config"      => [
                        'username'     => env('ROS_USERNAME'),
                        'password'     => env('ROS_PASSWORD'),
                        'tenant_name'  => env('ROS_TENANT_NAME'),
                        'api_key'      => env('ROS_API_KEY'),
                        'url'          => env('ROS_URL'),
                        'region'       => env('ROS_REGION'),
                        'storage_type' => env('ROS_STORAGE_TYPE'),
                        'container'    => env('ROS_CONTAINER')
                    ]
                ]
            );
        }
    }

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTContainerWithCheckExist()
    {
//        $payload = '{"name":"' . static::FOLDER_2 . '"}';
//
//        $rs = $this->makeRequest(Verbs::POST, null, [], $payload);
//        $this->assertEquals(
//            '{"name":"' . static::FOLDER_2 . '","path":"' . static::FOLDER_2 . '"}',
//            json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES)
//        );

        //Check_exist is not currently supported by Rackspace Could Files implementation.
        //$rs = $this->_call(Verbs::POST, $this->prefix."?check_exist=true", $payload);
        //$this->assertResponseStatus(400);
        //$this->assertContains("Container 'beta15lam' already exists.", $rs->getContent());
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGETContainerIncludeProperties()
    {
        $this->assertEquals(1, 1);
        //This feature is not currently supported  by Rackspace Could Files implementation.
        //$rs = $this->call(Verbs::GET, $this->prefix."?include_properties=true");
    }
}
