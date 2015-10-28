<?php
namespace DreamFactory\Core\Salesforce\Models;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\BaseServiceConfigModel;
use Illuminate\Database\Query\Builder;

/**
 * SalesforceConfig
 *
 * @property integer $service_id
 * @property string  $dsn
 * @property string  $options
 * @property string  $driver_options
 *
 * @method static Builder|SalesforceConfig whereServiceId($value)
 */
class SalesforceConfig extends BaseServiceConfigModel
{
    protected $table = 'salesforce_db_config';

    protected $fillable = ['service_id', 'username', 'password', 'security_token'];

    protected $encrypted = ['password', 'security_token'];

    public static function validateConfig($config, $create = true)
    {
        if (null === ArrayUtils::get($config, 'username', null, true) ||
            null === ArrayUtils::get($config, 'password', null, true)
        ) {
            throw new BadRequestException('Both Username and Password are required');
        }

        return true;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'username':
                $schema['label'] = 'Username';
                $schema['description'] = 'Username required to connect to Salesforce database.';
                break;
            case 'password':
                $schema['label'] = 'Password';
                $schema['description'] = 'Password required to connect to Salesforce database.';
                break;
            case 'security_token':
                $schema['label'] = 'Security Token';
                $schema['description'] = 'Security token for your Salesforce account.';
                break;
        }
    }
}