<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\Models\ServiceCacheConfig;
use Guzzle\Http\Message\Header;

class RwsConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_config';

    protected $fillable = ['service_id', 'base_url'];

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getConfig($id)
    {
        $config = parent::getConfig($id);

        $params = ParameterConfig::whereServiceId($id)->get();
        $config['parameters'] = (empty($params)) ? [] : $params->toArray();
        $headers = HeaderConfig::whereServiceId($id)->get();
        $config['headers'] = (empty($headers)) ? [] : $headers->toArray();
        $cacheConfig = ServiceCacheConfig::whereServiceId($id)->first();
        $config['cache_enabled'] = (empty($cacheConfig)) ? false : $cacheConfig->getAttribute('cache_enabled');
        $config['cache_ttl'] = (empty($cacheConfig)) ? 0 : $cacheConfig->getAttribute('cache_ttl');

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        if (isset($config['parameters'])) {
            $params = $config['parameters'];
            if (!is_array($params)) {
                throw new BadRequestException('Web service parameters must be an array.');
            }
            foreach ($params as $param) {
                if (!ParameterConfig::validateConfig($param, $create)) {
                    return false;
                }
            }
        }
        if (isset($config['headers'])) {
            $headers = $config['headers'];
            if (!is_array($headers)) {
                throw new BadRequestException('Web service headers must be an array.');
            }
            foreach ($headers as $header) {
                if (!HeaderConfig::validateConfig($header, $create)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        if (isset($config['parameters'])) {
            $params = $config['parameters'];
            if (!is_array($params)) {
                throw new BadRequestException('Web service parameters must be an array.');
            }
            ParameterConfig::setConfig($id, $params);
        }

        if (isset($config['headers'])) {
            $headers = $config['headers'];
            if (!is_array($headers)) {
                throw new BadRequestException('Web service headers must be an array.');
            }
            HeaderConfig::setConfig($id, $headers);
        }

        $cache = [];
        if (isset($config['cache_enabled'])) {
            $cache['cache_enabled'] = $config['cache_enabled'];
            unset($config['cache_enabled']);
        }
        if (isset($config['cache_ttl'])) {
            $cache['cache_ttl'] = $config['cache_ttl'];
            unset($config['cache_ttl']);
        }
        if (!empty($cache)) {
            ServiceCacheConfig::setConfig($id, $cache);
        }

        parent::setConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema[] = ParameterConfig::getConfigSchema();
        $schema[] = HeaderConfig::getConfigSchema();
        $schema = array_merge($schema, ServiceCacheConfig::getConfigSchema());

        return $schema;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'base_url':
                $schema['label'] = 'Base URL';
                $schema['type'] = 'text';
                $schema['description'] =
                    'This is the root for the external call, additional resource path and parameters from client, ' .
                    'along with provisioned parameters and headers, will be added.';
                break;
        }
    }
}