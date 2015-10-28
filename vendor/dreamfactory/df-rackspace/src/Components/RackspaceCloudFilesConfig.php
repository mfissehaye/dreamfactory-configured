<?php
namespace DreamFactory\Core\Rackspace\Components;

use DreamFactory\Core\Components\FileServiceWithContainer;
use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Core\Models\FilePublicPath;
use DreamFactory\Core\Rackspace\Models\RackspaceConfig;
use DreamFactory\Library\Utility\ArrayUtils;

class RackspaceCloudFilesConfig implements ServiceConfigHandlerInterface
{
    use FileServiceWithContainer;

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getConfig($id)
    {
        $rosConfig = RackspaceConfig::find($id);
        $pathConfig = FilePublicPath::find($id);

        $config = [];

        if (!empty($rosConfig)) {
            $config = $rosConfig->toArray();
        }

        if (!empty($pathConfig)) {
            $config = array_merge($config, $pathConfig->toArray());
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        return (RackspaceConfig::validateConfig($config, $create) && FilePublicPath::validateConfig($config, $create));
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        $rosConfig = RackspaceConfig::find($id);
        $pathConfig = FilePublicPath::find($id);
        $configPath = [
            'public_path' => ArrayUtils::get($config, 'public_path'),
            'container'   => ArrayUtils::get($config, 'container')
        ];
        $configRos = [
            'service_id'   => ArrayUtils::get($config, 'service_id'),
            'username'     => ArrayUtils::get($config, 'username'),
            'password'     => ArrayUtils::get($config, 'password'),
            'tenant_name'  => ArrayUtils::get($config, 'tenant_name'),
            'api_key'      => ArrayUtils::get($config, 'api_key'),
            'url'          => ArrayUtils::get($config, 'url'),
            'region'       => ArrayUtils::get($config, 'region'),
            'storage_type' => 'rackspace cloudfiles'
        ];

        ArrayUtils::removeNull($configRos);
        ArrayUtils::removeNull($configPath);

        if (!empty($rosConfig)) {
            $rosConfig->update($configRos);
        } else {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $configRos = array_reverse($configRos, true);
            $configRos['service_id'] = $id;
            $configRos = array_reverse($configRos, true);
            RackspaceConfig::create($configRos);
        }

        if (!empty($pathConfig)) {
            $pathConfig->update($configPath);
        } else {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $configPath = array_reverse($configPath, true);
            $configPath['service_id'] = $id;
            $configPath = array_reverse($configPath, true);
            FilePublicPath::create($configPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $rosConfig = new RackspaceConfig();
        $pathConfig = new FilePublicPath();
        $out = null;

        $rosSchema = $rosConfig->getConfigSchema();
        $pathSchema = $pathConfig->getConfigSchema();

        static::updatePathSchema($pathSchema);

        if (!empty($rosSchema)) {
            $out = $rosSchema;
        }
        if (!empty($pathSchema)) {
            $out = ($out) ? array_merge($out, $pathSchema) : $pathSchema;
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public static function removeConfig($id)
    {
        // deleting is not necessary here due to cascading on_delete relationship in database
    }

    /**
     * {@inheritdoc}
     */
    public static function getAvailableConfigs()
    {
        return null;
    }
}