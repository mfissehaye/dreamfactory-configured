<?php
namespace DreamFactory\Core\Salesforce\Resources;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\BaseDbSchemaResource;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;

class Schema extends BaseDbSchemaResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var null|SalesforceDb
     */
    protected $parent = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return null|SalesforceDb
     */
    public function getService()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function describeTable($table, $refresh = true)
    {
        $name = (is_array($table)) ? ArrayUtils::get($table, 'name') : $table;

        try {
            $result = $this->parent->callGuzzle('GET', 'sobjects/' . $table . '/describe');

            $out = $result;
            $out['access'] = $this->getPermissions($name);

            return $out;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException(
                "Failed to get table properties for table '$name'.\n{$ex->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeField($table, $field, $refresh = false)
    {
        $result = $this->describeTable($table);
        $fields = ArrayUtils::get($result, 'fields');
        if (empty($fields)) {
            foreach ($fields as $item) {
                if (ArrayUtils::get($item, 'name') == $field) {
                    return $item;
                }
            }
        }

        throw new NotFoundException("Field '$field' not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function createTable($table, $properties = array(), $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable($table, $properties = array(), $allow_delete_fields = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable($table, $check_empty = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function createField($table, $field, $properties = array(), $check_exist = false, $return_schema = false)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function updateField(
        $table,
        $field,
        $properties = array(),
        $allow_delete_parts = false,
        $return_schema = false
    ){
        throw new NotImplementedException("Metadata actions currently not supported.");
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField($table, $field)
    {
        throw new NotImplementedException("Metadata actions currently not supported.");
    }
}