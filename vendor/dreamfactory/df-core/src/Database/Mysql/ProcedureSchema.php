<?php
namespace DreamFactory\Core\Database\Mysql;

/**
 * ProcedureSchema represents the metadata for a MySQL stored procedure.
 */
class ProcedureSchema extends \DreamFactory\Core\Database\ProcedureSchema
{
    /**
     * @var string name of the schema (database) that this procedure belongs to.
     * Defaults to null, meaning no schema (or the current database).
     */
    public $schemaName;
}
