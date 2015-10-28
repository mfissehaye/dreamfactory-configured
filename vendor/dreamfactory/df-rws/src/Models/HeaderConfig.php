<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

/**
 * HeaderConfig
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property boolean pass_from_client
 * @property integer $action
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereId($value)
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereName($value)
 * @method static \Illuminate\Database\Query\Builder|HeaderConfig whereServiceId($value)
 */
class HeaderConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_headers_config';

    protected $fillable = ['service_id', 'name', 'value', 'pass_from_client', 'action'];

    protected $casts = [
        'pass_from_client' => 'boolean',
        'id'               => 'integer',
        'service_id'       => 'integer',
        'action'           => 'integer'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getConfig($id)
    {
        $params = static::whereServiceId($id);

        if (!empty($params)) {
            return $params->toArray();
        } else {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        static::whereServiceId($id)->delete();
        if (!empty($config)) {
            foreach ($config as $header) {
                //Making sure service_id is the first item in the config.
                //This way service_id will be set first and is available
                //for use right away. This helps setting an auto-generated
                //field that may depend on parent data. See OAuthConfig->setAttribute.
                $header = array_reverse($header, true);
                $header['service_id'] = $id;
                $header = array_reverse($header, true);
                static::create($header);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema =
            [
                'name'        => 'headers',
                'label'       => 'Headers',
                'description' => 'Supply additional headers to pass to the remote service.',
                'type'        => 'array',
                'required'    => false,
                'allow_null'  => true
            ];
        $schema['items'] = parent::getConfigSchema();

        return $schema;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'action':
                $schema['label'] = 'Verbs';
                $schema['type'] = 'verb_mask';
                $schema['description'] =
                    'Which REST verbs should this header be applied to outgoing requests.';
                break;
        }
    }
}