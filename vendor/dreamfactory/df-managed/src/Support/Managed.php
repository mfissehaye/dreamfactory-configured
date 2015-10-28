<?php namespace DreamFactory\Managed\Support;

use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Disk;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Managed\Enums\AuditLevels;
use DreamFactory\Managed\Enums\ManagedDefaults;
use DreamFactory\Managed\Facades\Audit;
use DreamFactory\Managed\Services\AuditingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Methods for interfacing with DreamFactory Enterprise (DFE)
 *
 * This class discovers if this instance is a DFE cluster participant. When the DFE
 * console provisions an instance, the cluster configuration file is used to determine
 * the necessary information to operate in a managed environment.
 */
final class Managed
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string Cache key in the config
     */
    const CACHE_CONFIG_KEY = 'cache.stores.file.path';
    /**
     * @type string Prepended to the cache keys of this object
     */
    const CACHE_KEY_PREFIX = 'df.managed.config.';
    /**
     * @type int The number of minutes to keep managed instance data cached
     */
    const CACHE_TTL = ManagedDefaults::CONFIG_CACHE_TTL;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string Our API access token
     */
    protected static $accessToken;
    /**
     * @type string
     */
    protected static $cacheKey;
    /**
     * @type array
     */
    protected static $config = [];
    /**
     * @type bool
     */
    protected static $managed = false;
    /**
     * @type array The storage paths
     */
    protected static $paths = [];
    /**
     * @type string The root storage directory
     */
    protected static $storageRoot;
    /**
     * @type bool If true, log files will be written to the temp space
     */
    protected static $logToTemp = true;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Initialization for hosted DSPs
     *
     * @return array
     * @throws \RuntimeException
     */
    public static function initialize()
    {
        static::initializeDefaults();

        //  If this is a stand-alone instance, just bail now.
        if (config('df.standalone', true)) {
            return false;
        }

        if (!static::loadCachedValues()) {
            //  Discover where I am
            if (!static::getClusterConfiguration()) {
                //  Unmanaged instance, ignoring
                return false;
            }

            try {
                //  Discover our secret powers...
                static::interrogateCluster();
            } catch (\RuntimeException $_ex) {
                /** @noinspection PhpUndefinedMethodInspection */
                Log::error('Error interrogating console: ' . $_ex->getMessage());

                return false;
            }
        }

        //logger('Managed instance bootstrap complete.');

        return static::$managed = true;
    }

    /**
     * @param \Illuminate\Http\Request $request     The original request
     * @param array|null               $sessionData Optional session data
     * @param int                      $level       The level of the audit record. Defaults to INFO
     * @param string                   $facility    The facility. No longer part of GELF, or used by DFE, but kept for compatibility
     */
    public static function auditRequest(Request $request, $sessionData = null, $level = AuditLevels::INFO, $facility = AuditingService::DEFAULT_FACILITY)
    {
        if (static::isManagedInstance()) {
            if (null === $sessionData || !is_array($sessionData)) {
                /** @noinspection PhpUndefinedMethodInspection */
                $sessionData = Session::all();
            }

            array_forget($sessionData, ['_token', 'token', 'api_key', 'session_token']);
            $sessionData['metadata'] = static::getConfig('audit', []);

            Audit::logRequest(static::getInstanceName(),
                $request,
                $sessionData,
                $level,
                $facility);
        }
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    protected static function getClusterConfiguration($key = null, $default = null)
    {
        $configFile = static::locateClusterEnvironmentFile(ManagedDefaults::CLUSTER_MANIFEST_FILE);

        if (!$configFile || !file_exists($configFile)) {
            return false;
        }

        try {
            static::$config = JsonFile::decodeFile($configFile);

            //logger('Cluster config read from ' . $configFile);

            //  Cluster validation determines if an instance is managed or not
            if (!static::validateConfiguration()) {
                return false;
            }
        } catch (\Exception $_ex) {
            static::$config = [];

            //logger('Cluster configuration file is not in a recognizable format.');

            throw new \RuntimeException('This instance is not configured properly for your system environment.');
        }

        return null === $key ? static::$config : static::getConfig($key, $default);
    }

    /**
     * Retrieves an instance's status and caches the shaped result
     *
     * @return array|bool
     */
    protected static function interrogateCluster()
    {
        //  Generate a signature for signing payloads...
        static::$accessToken = static::generateSignature();

        //  Get my config from console
        $_status = static::callConsole('status', ['id' => $_id = static::getInstanceName()]);

        if (!($_status instanceof \stdClass) || !data_get($_status, 'response.metadata')) {
            throw new \RuntimeException('Corrupt response during status query for "' . $_id . '".',
                Response::HTTP_SERVICE_UNAVAILABLE);
        }

        //logger('Ops/status response code: ' . $_status->status_code);

        if (!$_status->success) {
            throw new \RuntimeException('Unmanaged instance detected.', Response::HTTP_NOT_FOUND);
        }

        if (data_get($_status, 'response.archived', false) || data_get($_status, 'response.deleted', false)) {
            throw new \RuntimeException('Instance "' . $_id . '" has been archived and/or deleted.',
                Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //  Stuff all the unadulterated data into the config
        $_paths = (array)data_get($_status, 'response.metadata.paths', []);
        $_paths['storage-root'] = static::$storageRoot = static::getConfig('storage-root', storage_path());

        static::setConfig([
            //  Storage root is the top-most directory under which all instance storage lives
            'storage-root'  => static::$storageRoot,
            //  The storage map defines where exactly under $storageRoot the instance's storage resides
            'storage-map'   => (array)data_get($_status, 'response.metadata.storage-map', []),
            'home-links'    => (array)data_get($_status, 'response.home-links'),
            'managed-links' => (array)data_get($_status, 'response.managed-links'),
            'env'           => $_env = (array)data_get($_status, 'response.metadata.env', []),
            'audit'         => $_audit = (array)data_get($_status, 'response.metadata.audit', []),
        ]);

        //  Clean up the paths accordingly
        $_paths['log-path'] = Disk::segment([
            array_get($_paths, 'private-path', ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME),
            ManagedDefaults::PRIVATE_LOG_PATH_NAME,
        ],
            false);

        //  prepend real base directory to all collected paths and cache statically
        foreach (array_except($_paths, ['storage-root', 'storage-map']) as $_key => $_path) {
            $_paths[$_key] = Disk::path([static::$storageRoot, $_path], true, 0777, true);
        }

        //  Now place our paths into the config
        static::setConfig('paths', (array)$_paths);

        //  Get the database config plucking the first entry if one.
        static::setConfig('db', (array)head((array)data_get($_status, 'response.metadata.db', [])));

        if (!empty($_limits = (array)data_get($_status, 'response.metadata.limits', []))) {
            static::setConfig('limits', $_limits);
        }

        //  Set up our audit destination
        if (!empty($_env) && isset($_env['audit-host'], $_env['audit-port'])) {
            Audit::setHost(array_get($_env, 'audit-host'));
            Audit::setPort(array_get($_env, 'audit-port'));
        }

        static::freshenCache();

        return true;
    }

    /**
     * Validates that the required values are in static::$config
     *
     * @return bool
     */
    protected static function validateConfiguration()
    {
        try {
            //  Can we build the API url
            if (!isset(static::$config['console-api-url'], static::$config['console-api-key'])) {
                /** @noinspection PhpUndefinedMethodInspection */
                Log::error('Invalid configuration: No "console-api-url" or "console-api-key" in cluster manifest.');

                return false;
            }

            //  Make it ready for action...
            static::setConfig('console-api-url', rtrim(static::getConfig('console-api-url'), '/') . '/');

            //  And default domain
            $_host = static::getHostName();

            if (!empty($_defaultDomain = ltrim(static::getConfig('default-domain'), '. '))) {
                $_defaultDomain = '.' . $_defaultDomain;

                //	If this isn't an enterprise instance, bail
                if (false === strpos($_host, $_defaultDomain)) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    Log::error('Invalid "default-domain" for host "' . $_host . '"');

                    return false;
                }

                static::setConfig('default-domain', $_defaultDomain);
            }

            if (empty($storageRoot = static::getConfig('storage-root'))) {
                /** @noinspection PhpUndefinedMethodInspection */
                Log::error('No "storage-root" found.');

                return false;
            }

            static::setConfig([
                'storage-root'  => rtrim($storageRoot, ' ' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                'instance-name' => str_replace($_defaultDomain, null, $_host),
            ]);

            //  Pull out the audit info...
            $_env = static::getConfig('env', []);

            //  Set up our audit destination
            if (!empty($_env) && isset($_env['audit-host'], $_env['audit-port'])) {
                Audit::getLogger()->setHost(array_get($_env, 'audit-host'));
                Audit::getLogger()->setPort(array_get($_env, 'audit-port'));
            }

            //  It's all good!
            return true;
        } catch (\InvalidArgumentException $_ex) {
            //  The file is bogus or not there
            return false;
        }
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    protected static function callConsole($uri, $payload = [], $curlOptions = [], $method = Request::METHOD_POST)
    {
        try {
            //  Allow full URIs or manufacture one...
            if ('http' != substr($uri, 0, 4)) {
                $uri = static::$config['console-api-url'] . ltrim($uri, '/ ');
            }

            if (false === ($_result = Curl::request($method, $uri, static::signPayload($payload), $curlOptions))) {
                throw new \RuntimeException('Failed to contact API server.');
            }

            if (!($_result instanceof \stdClass)) {
                if (is_string($_result) && (false === json_decode($_result) || JSON_ERROR_NONE !== json_last_error())) {
                    throw new \RuntimeException('Invalid response received from DFE console.');
                }
            }

            return $_result;
        } catch (\Exception $_ex) {
            /** @noinspection PhpUndefinedMethodInspection */
            Log::error('Console API Error: ' . $_ex->getMessage());

            return false;
        }
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    protected static function signPayload(array $payload)
    {
        return array_merge([
            'client-id'    => static::$config['client-id'],
            'access-token' => static::$accessToken,
        ],
            $payload ?: []);
    }

    /**
     * @return string
     */
    protected static function generateSignature()
    {
        return hash_hmac(static::$config['signature-method'],
            static::$config['client-id'],
            static::$config['client-secret']);
    }

    /**
     * @return boolean
     */
    public static function isManagedInstance()
    {
        empty(static::$cacheKey) && static::initialize();

        return static::$managed;
    }

    /**
     * @return string
     */
    public static function getInstanceName()
    {
        return static::getConfig('instance-name');
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getStoragePath($append = null)
    {
        return Disk::path([array_get(static::$paths, 'storage-path'), $append], true);
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getPrivatePath($append = null)
    {
        return Disk::path([array_get(static::$paths, 'private-path'), $append], true);
    }

    /**
     * @return string Absolute /path/to/logs
     */
    public static function getLogPath()
    {
        return static::$logToTemp
            ? Disk::path([sys_get_temp_dir(), '.df-log'])
            : Disk::path([
                array_get(static::$paths,
                    'log-path'),
            ],
                true);
    }

    /**
     * @param string|null $name
     *
     * @return string The absolute /path/to/log/file
     */
    public static function getLogFile($name = null)
    {
        return Disk::path([static::getLogPath(), ($name ?: 'dreamfactory-' . static::getHostName() . '.log')]);
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getOwnerPrivatePath($append = null)
    {
        return Disk::path([array_get(static::$paths, 'owner-private-path'), $append], true);
    }

    /**
     * Retrieve a config value or the entire array
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    public static function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return static::$config;
        }

        $_value = array_get(static::$config, $key, $default);

        //  Add value to array if defaulted
        $_value === $default && static::setConfig($key, $_value);

        return $_value;
    }

    /**
     * Retrieve a config value or the entire array
     *
     * @param string|array $key A single key to set or an array of KV pairs to set at once
     * @param mixed        $value
     *
     * @return array|mixed
     */
    protected static function setConfig($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $_key => $_value) {
                array_set(static::$config, $_key, $_value);
            }

            return static::$config;
        }

        return array_set(static::$config, $key, $value);
    }

    /**
     * Reload the cache
     */
    protected static function loadCachedValues()
    {
        // Need to set the cache path before every cache operation to make sure the cache does not get
        // shared between instances
        config([static::CACHE_CONFIG_KEY => static::getCachePath()]);

        /** @noinspection PhpUndefinedMethodInspection */
        $_cache = Cache::get(static::$cacheKey);

        if (!empty($_cache) && is_array($_cache)) {
            static::$config = $_cache;
            static::$paths = static::getConfig('paths');

            return static::validateConfiguration();
        }

        return false;
    }

    /**
     * Refreshes the cache with fresh values
     */
    protected static function freshenCache()
    {
        config([static::CACHE_CONFIG_KEY => static::getCachePath()]);

        /** @noinspection PhpUndefinedMethodInspection */
        Cache::put(static::getCacheKey(), static::$config, static::CACHE_TTL);

        static::$paths = static::getConfig('paths', []);
    }

    /**
     * Locate the configuration file for DFE, if any
     *
     * @param string $file
     *
     * @return bool|string
     */
    protected static function locateClusterEnvironmentFile($file)
    {
        $_path = isset($_SERVER, $_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd();

        while (true) {
            if (file_exists($_path . DIRECTORY_SEPARATOR . $file)) {
                return $_path . DIRECTORY_SEPARATOR . $file;
            }

            $_parentPath = dirname($_path);

            if ($_parentPath == $_path || empty($_parentPath) || $_parentPath == DIRECTORY_SEPARATOR) {
                return false;
            }

            $_path = $_parentPath;
        }

        return false;
    }

    /**
     * Gets my host name
     *
     * @param bool $hashed If true, an md5 hash of the host name will be returned
     *
     * @return string
     */
    protected static function getHostName($hashed = false)
    {
        $_defaultHost = ((isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : gethostname());
        $_host = static::getConfig('managed.host-name', $_defaultHost);

        return $hashed ? md5($_host) : $_host;
    }

    /**
     * Returns a key prefixed for use in \Cache
     *
     * @return string
     */
    protected static function getCacheKey()
    {
        return static::$cacheKey = static::$cacheKey ?: static::CACHE_KEY_PREFIX . static::getHostName();
    }

    /**
     * Returns cache path qualified by hostname
     *
     * @return string
     */
    public static function getCachePath()
    {
        return Disk::path([static::getCacheRoot(), static::getHostName(true)]);
    }

    /**
     * Returns a key prefixed for use in \Cache
     *
     * @return string
     */
    public static function getCacheKeyPrefix()
    {
        return 'dreamfactory' . static::getHostName(true) . ':';
    }

    /**
     * Return a database configuration as specified by the console if managed, or config() otherwise.
     *
     * @return array
     */
    public static function getDatabaseConfig()
    {
        return static::isManagedInstance()
            ? static::getConfig('db')
            : config('database.connections.' . config('database.default'),
                []);
    }

    /**
     * Return the limits for this instance or an empty array if none.
     *
     * @param string|null $limitKey A key within the limits to retrieve. If omitted, all limits are returned
     * @param array       $default  The default value to return if $limitKey was not found
     *
     * @return array|mixed
     */
    public static function getLimits($limitKey = null, $default = [])
    {
        return null === $limitKey
            ? static::getConfig('limits', [])
            : array_get(static::getConfig('limits', []),
                $limitKey,
                $default);
    }

    /**
     * Return the Console API Key hash or null
     *
     * @return string|null
     */
    public static function getConsoleKey()
    {
        return static::isManagedInstance() ? hash(ManagedDefaults::DEFAULT_SIGNATURE_METHOD,
            IfSet::getDeep(static::$config, 'env', 'cluster-id') . IfSet::getDeep(static::$config,
                'env',
                'instance-id')) : null;
    }

    /**
     * Returns the storage root path
     *
     * @return string
     */
    public static function getStorageRoot()
    {
        return static::$storageRoot;
    }

    /** Returns cache root */
    public static function getCacheRoot()
    {
        return Disk::path([sys_get_temp_dir(), '.df-cache']);
    }

    /**
     * Initialize defaults for a stand-alone instance and sets the cache key
     *
     * @param string|null $storagePath A storage path to use instead of storage_path()
     */
    protected static function initializeDefaults($storagePath = null)
    {
        static::getCacheKey();

        $_storagePath = Disk::path([$storagePath ?: storage_path()]);

        static::$paths = [
            'storage-root'       => $_storagePath,
            'storage-path'       => $_storagePath,
            'private-path'       => Disk::path([$_storagePath, ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME]),
            'owner-private-path' => Disk::path([$_storagePath, ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME]),
            'log-path'           => Disk::path([$_storagePath, ManagedDefaults::PRIVATE_LOG_PATH_NAME]),
            'snapshot-path'      => Disk::path([
                $_storagePath,
                ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME,
                ManagedDefaults::SNAPSHOT_PATH_NAME,
            ]),
        ];

        static::$managed = false;
    }
}
