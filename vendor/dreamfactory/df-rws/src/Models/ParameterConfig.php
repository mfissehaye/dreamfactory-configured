<?php
namespace DreamFactory\Core\Rws\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

/**
 * ParameterConfig
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property boolean $exclude
 * @property boolean $outbound
 * @property string  $cache_key
 * @property integer $action
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereId($value)
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereName($value)
 * @method static \Illuminate\Database\Query\Builder|ParameterConfig whereServiceId($value)
 */
class ParameterConfig extends BaseServiceConfigModel
{
    protected $table = 'rws_parameters_config';

    protected $fillable = ['service_id', 'name', 'value', 'exclude', 'outbound', 'cache_key', 'action'];

    protected $casts = [
        'exclude'    => 'boolean',
        'outbound'   => 'boolean',
        'cache_key'  => 'boolean',
        'id'         => 'integer',
        'service_id' => 'integer',
        'action'     => 'integer'
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
            foreach ($config as $param) {
                //Making sure service_id is the first item in the config.
                //This way service_id will be set first and is available
                //for use right away. This helps setting an auto-generated
                //field that may depend on parent data. See OAuthConfig->setAttribute.
                $param = array_reverse($param, true);
                $param['service_id'] = $id;
                $param = array_reverse($param, true);
                static::create($param);
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
                'name'        => 'parameters',
                'label'       => 'Parameters',
                'description' => 'Supply additional parameters to pass to the remote service, or exclude parameters passed from client.',
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
                    'Which REST verbs should this parameter be applied to.';
                break;
        }
    }
}