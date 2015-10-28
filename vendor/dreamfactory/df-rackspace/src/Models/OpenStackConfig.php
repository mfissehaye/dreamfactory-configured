<?php
namespace DreamFactory\Core\Rackspace\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;
use DreamFactory\Core\Exceptions\BadRequestException;

/**
 * Class RackspaceConfig
 *
 * @package DreamFactory\Core\Rackspace\Models
 */
class OpenStackConfig extends BaseServiceConfigModel
{
    protected $table = 'rackspace_config';

    protected $encrypted = ['password', 'api_key'];

    protected $fillable = [
        'service_id',
        'username',
        'password',
        'tenant_name',
        'api_key',
        'url',
        'region',
        'storage_type'
    ];

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'username'    => 'required',
            'password'    => 'required',
            'tenant_name' => 'required',
            'url'         => 'required',
            'region'      => 'required'
        ], $create);

        if ($validator->fails()) {
            $messages = $validator->messages()->getMessages();
            throw new BadRequestException('Validation failed.', null, null, $messages);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $model = new static;

        $schema = $model->getTableSchema();
        if ($schema) {
            $out = [];
            foreach ($schema->columns as $name => $column) {
                /** @var ColumnSchema $column */
                if (('service_id' === $name) ||
                    'api_key' === $name ||
                    'storage_type' === $name ||
                    $column->autoIncrement
                ) {
                    continue;
                }

                $temp = $column->toArray();
                static::prepareConfigSchemaField($temp);
                $out[] = $temp;
            }

            return $out;
        }

        return null;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'username':
                $schema['description'] = 'The user name for the service connection.';
                break;
            case 'password':
                $schema['description'] = 'The password for the service connection.';
                break;
            case 'tenant_name':
                $schema['description'] = 'Normally your account number.';
                break;
            case 'url':
                $schema['label'] = 'URL';
                $schema['description'] = 'The URL/endpoint for the service connection.';
                break;
            case 'region':
                $schema['type'] = 'picklist';
                $schema['values'] = [
                    ['label' => 'Chicago', 'name' => 'ORD', 'url' => 'https://identity.api.rackspacecloud.com'],
                    ['label' => 'Dallas', 'name' => 'DFW', 'url' => 'https://identity.api.rackspacecloud.com'],
                    ['label' => 'London', 'name' => 'LON', 'url' => 'https://lon.identity.api.rackspacecloud.com'],
                ];
                $schema['description'] = 'Select the region to be accessed by this service connection.';
                break;
        }
    }

}