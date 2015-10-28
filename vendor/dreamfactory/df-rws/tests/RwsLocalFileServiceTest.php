<?php

class RwsLocalFileServiceTest extends \DreamFactory\Core\Testing\FileServiceTestCase
{
    protected static $staged = false;

    public function stage()
    {
        parent::stage();

        Artisan::call('migrate', ['--path' => 'vendor/dreamfactory/df-rws/database/migrations/']);
        Artisan::call('db:seed', ['--class' => DreamFactory\Core\Rws\Database\Seeds\DatabaseSeeder::class]);

        if (!$this->serviceExists('rave')) {
            \DreamFactory\Core\Models\Service::create(
                [
                    "name"   => "rave",
                    "type"   => "rws",
                    "label"  => "Remote web service",
                    "config" => [
                        "base_url"      => "http://df.local/rest",
                        "cache_enabled" => false
                    ]
                ]
            );
        }
    }

    protected function setService()
    {
        $this->service = 'rave/files';
        $this->prefix = $this->prefix . '/' . $this->service;
    }
}