<?php namespace DreamFactory\Managed\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * Constants for managed instances
 */
class ManagedDefaults extends FactoryEnum
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type int The number of minutes to hold managed configurations in cache
     */
    const CONFIG_CACHE_TTL = 5;
    /**
     * @type string
     */
    const CONSOLE_X_HEADER = 'X-DreamFactory-Console-Key';
    /**
     * @type string
     */
    const DF_API_KEY = 'X-DreamFactory-Api-Key';
    /**
     * @type string The name of the cluster manifest file
     */
    const CLUSTER_MANIFEST_FILE = '.dfe.cluster.json';
    /**
     * @type string The name of the "private" path
     */
    const DEFAULT_PRIVATE_PATH_NAME = '.private';
    /**
     * @type string The default signature hash algorithm
     */
    const DEFAULT_SIGNATURE_METHOD = 'sha256';
    /**
     * @type string
     */
    const DFE_MARKER = '/var/www/.dfe-managed';
    /**
     * @type string
     */
    const MAINTENANCE_MARKER = '/var/www/.maintenance';
    /**
     * @type string
     */
    const MANAGED_INSTANCE_MARKER = '/var/www/.dfe-managed';
    /**
     * @type string The cache path name
     */
    const PRIVATE_CACHE_PATH_NAME = '.cache';
    /**
     * @type string The default path where logs go under $storagePath
     */
    const PRIVATE_LOG_PATH_NAME = 'logs';
    /**
     * @type string The name of the path under owner-private-path to contain snapshots
     */
    const SNAPSHOT_PATH_NAME = 'snapshots';
}
