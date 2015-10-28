<?php
namespace DreamFactory\Core\Salesforce\Resources;

use DreamFactory\Core\Database\ColumnSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseDbTableResource;
use DreamFactory\Core\Salesforce\Services\SalesforceDb;

class Table extends BaseDbTableResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'Id';

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
    public function retrieveRecordsByFilter($table, $filter = null, $params = [], $extras = [])
    {
        $fields = ArrayUtils::get($extras, ApiOptions::FIELDS);
        $idField = ArrayUtils::get($extras, ApiOptions::ID_FIELD);
        $fields = $this->buildFieldList($table, $fields, $idField);

        $next = ArrayUtils::get($extras, 'next');
        if (!empty($next)) {
            $result = $this->parent->callGuzzle('GET', 'query/' . $next);
        } else {
            // build query string
            $query = 'SELECT ' . $fields . ' FROM ' . $table;

            if (!empty($filter)) {
                $query .= ' WHERE ' . $filter;
            }

            $order = ArrayUtils::get($extras, ApiOptions::ORDER);
            if (!empty($order)) {
                $query .= ' ORDER BY ' . $order;
            }

            $offset = intval(ArrayUtils::get($extras, ApiOptions::OFFSET, 0));
            if ($offset > 0) {
                $query .= ' OFFSET ' . $offset;
            }

            $limit = intval(ArrayUtils::get($extras, ApiOptions::LIMIT, 0));
            if ($limit > 0) {
                $query .= ' LIMIT ' . $limit;
            }

            $result = $this->parent->callGuzzle('GET', 'query', ['q' => $query]);
        }

        $data = ArrayUtils::get($result, 'records', []);

        $includeCount = ArrayUtils::getBool($extras, ApiOptions::INCLUDE_COUNT, false);
        $moreToken = ArrayUtils::get($result, 'nextRecordsUrl');
        if ($includeCount || $moreToken) {
            // count total records
            $data['meta']['count'] = intval(ArrayUtils::get($result, 'totalSize'));
            if ($moreToken) {
                $data['meta']['next'] = substr($moreToken, strrpos($moreToken, '/') + 1);
            }
        }

        return $data;
    }

    protected function getFieldsInfo($table)
    {
        $result = $this->parent->callGuzzle('GET', 'sobjects/' . $table . '/describe');
        $result = ArrayUtils::get($result, ApiOptions::FIELDS);
        if (empty($result)) {
            return [];
        }

        $fields = [];
        foreach ($result as $field) {
            $fields[] = new ColumnSchema($field);
        }

        return $fields;
    }

    protected function getIdsInfo($table, $fields_info = null, &$requested_fields = null, $requested_types = null)
    {
        $requested_fields = static::DEFAULT_ID_FIELD; // can only be this
        $requested_types = ArrayUtils::clean($requested_types);
        $type = ArrayUtils::get($requested_types, 0, 'string');
        $type = (empty($type)) ? 'string' : $type;

        return [new ColumnSchema(['name' => static::DEFAULT_ID_FIELD, 'type' => $type, 'required' => false])];
    }

    /**
     * @param      $table
     * @param bool $as_array
     *
     * @return array|string
     */
    protected function getAllFields($table, $as_array = false)
    {
        $result = $this->parent->callGuzzle('GET', 'sobjects/' . $table . '/describe');
        $result = ArrayUtils::get($result, ApiOptions::FIELDS);
        if (empty($result)) {
            return [];
        }

        $fields = [];
        foreach ($result as $field) {
            $fields[] = ArrayUtils::get($field, 'name');
        }

        if ($as_array) {
            return $fields;
        }

        return implode(',', $fields);
    }

    /**
     * @param      $table
     * @param null $fields
     * @param null $id_field
     *
     * @return array|null|string
     */
    protected function buildFieldList($table, $fields = null, $id_field = null)
    {
        if (empty($id_field)) {
            $id_field = static::DEFAULT_ID_FIELD;
        }

        if (empty($fields)) {
            $fields = $id_field;
        } elseif (ApiOptions::FIELDS_ALL == $fields) {
            $fields = $this->getAllFields($table);
        } else {
            if (is_array($fields)) {
                $fields = implode(',', $fields);
            }

            // make sure the Id field is always returned
            if (false === array_search(
                    strtolower($id_field),
                    array_map(
                        'trim',
                        explode(',', strtolower($fields))
                    )
                )
            ) {
                $fields = array_map('trim', explode(',', $fields));
                $fields[] = $id_field;
                $fields = implode(',', $fields);
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction(
        $record = null,
        $id = null,
        $extras = null,
        $rollback = false,
        $continue = false,
        $single = false
    ){
        $fields = ArrayUtils::get($extras, ApiOptions::FIELDS);
        $ssFilters = ArrayUtils::get($extras, 'ss_filters');
        $updates = ArrayUtils::get($extras, 'updates');
        $idFields = ArrayUtils::get($extras, 'id_fields');
        $needToIterate = ($single || $continue || (1 < count($this->tableIdsInfo)));
        $requireMore = ArrayUtils::getBool($extras, 'require_more');

        $client = $this->parent->getGuzzleClient();

        $out = [];
        switch ($this->getAction()) {
            case Verbs::POST:
                $parsed = $this->parseRecord($record, $this->tableFieldsInfo, $ssFilters);
                if (empty($parsed)) {
                    throw new BadRequestException('No valid fields were found in record.');
                }

                $native = json_encode($parsed);
                $result =
                    $this->parent->callGuzzle('POST', 'sobjects/' . $this->transactionTable . '/', null, $native,
                        $client);
                if (!ArrayUtils::getBool($result, 'success', false)) {
                    $msg = json_encode(ArrayUtils::get($result, 'errors'));
                    throw new InternalServerErrorException("Record insert failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                $id = ArrayUtils::get($result, 'id');

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::PUT:
            case Verbs::MERGE:
            case Verbs::PATCH:
                if (!empty($updates)) {
                    $record = $updates;
                }

                $parsed = $this->parseRecord($record, $this->tableFieldsInfo, $ssFilters, true);
                if (empty($parsed)) {
                    throw new BadRequestException('No valid fields were found in record.');
                }

                static::removeIds($parsed, $idFields);
                $native = json_encode($parsed);

                $result = $this->parent->callGuzzle(
                    'PATCH',
                    'sobjects/' . $this->transactionTable . '/' . $id,
                    null,
                    $native,
                    $client
                );
                if ($result && !ArrayUtils::getBool($result, 'success', false)) {
                    $msg = ArrayUtils::get($result, 'errors');
                    throw new InternalServerErrorException("Record update failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::DELETE:
                $result = $this->parent->callGuzzle(
                    'DELETE',
                    'sobjects/' . $this->transactionTable . '/' . $id,
                    null,
                    null,
                    $client
                );
                if ($result && !ArrayUtils::getBool($result, 'success', false)) {
                    $msg = ArrayUtils::get($result, 'errors');
                    throw new InternalServerErrorException("Record delete failed for table '$this->transactionTable'.\n" .
                        $msg);
                }

                // add via record, so batch processing can retrieve extras
                return ($requireMore) ? parent::addToTransaction($id) : [$idFields => $id];

            case Verbs::GET:
                if (!$needToIterate) {
                    return parent::addToTransaction(null, $id);
                }

                $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                $result = $this->parent->callGuzzle(
                    'GET',
                    'sobjects/' . $this->transactionTable . '/' . $id,
                    ['fields' => $fields]
                );
                if (empty($result)) {
                    throw new NotFoundException("Record with identifier '" . print_r($id, true) . "' not found.");
                }

                $out = $result;
                break;
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction($extras = null)
    {
        if (empty($this->batchRecords) && empty($this->batchIds)) {
            if (isset($this->transaction)) {
                $this->transaction->commit();
            }

            return null;
        }

        $fields = ArrayUtils::get($extras, ApiOptions::FIELDS);
        $idFields = ArrayUtils::get($extras, 'id_fields');

        $out = [];
        $action = $this->getAction();
        if (!empty($this->batchRecords)) {
            if (1 == count($this->tableIdsInfo)) {
                // records are used to retrieve extras
                // ids array are now more like records
                $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                $idList = "('" . implode("','", $this->batchRecords) . "')";
                $query =
                    'SELECT ' .
                    $fields .
                    ' FROM ' .
                    $this->transactionTable .
                    ' WHERE ' .
                    $idFields .
                    ' IN ' .
                    $idList;

                $result = $this->parent->callGuzzle('GET', 'query', ['q' => $query]);

                $out = ArrayUtils::get($result, 'records', []);
                if (empty($out)) {
                    throw new NotFoundException('No records were found using the given identifiers.');
                }
            } else {
                $out = $this->retrieveRecords($this->transactionTable, $this->batchRecords, $extras);
            }

            $this->batchRecords = [];
        } elseif (!empty($this->batchIds)) {
            switch ($action) {
                case Verbs::PUT:
                case Verbs::MERGE:
                case Verbs::PATCH:
                    break;

                case Verbs::DELETE:
                    break;

                case Verbs::GET:
                    $fields = $this->buildFieldList($this->transactionTable, $fields, $idFields);

                    $idList = "('" . implode("','", $this->batchIds) . "')";
                    $query =
                        'SELECT ' .
                        $fields .
                        ' FROM ' .
                        $this->transactionTable .
                        ' WHERE ' .
                        $idFields .
                        ' IN ' .
                        $idList;

                    $result = $this->parent->callGuzzle('GET', 'query', ['q' => $query]);

                    $out = ArrayUtils::get($result, 'records', []);
                    if (empty($out)) {
                        throw new NotFoundException('No records were found using the given identifiers.');
                    }

                    break;

                default:
                    break;
            }

            if (empty($out)) {
                $out = $this->batchIds;
            }

            $this->batchIds = [];
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if (!empty($this->rollbackRecords)) {
            switch ($this->getAction()) {
                case Verbs::POST:
                    break;

                case Verbs::PUT:
                case Verbs::PATCH:
                case Verbs::MERGE:
                case Verbs::DELETE:
                    break;

                default:
                    break;
            }

            $this->rollbackRecords = [];
        }

        return true;
    }
}