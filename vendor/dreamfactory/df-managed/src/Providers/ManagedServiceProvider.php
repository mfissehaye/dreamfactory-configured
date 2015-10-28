<?php namespace DreamFactory\Managed\Providers;

use DreamFactory\Managed\Services\ManagedService;
use DreamFactory\Managed\Support\Managed;
use Illuminate\Support\ServiceProvider;

/**
 * Register the virtual config manager service as a Laravel provider
 */
class ManagedServiceProvider extends ServiceProvider
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The name of the service in the IoC
     */
    const IOC_NAME = 'managed.config';
    /**
     * @type string The key in database config that holds all connections.
     */
    const DATABASE_ALL_CONNECTIONS_KEY = 'database.connections';
    /**
     * @type string The key in database config that specifies default connection.
     */
    const DATABASE_DEFAULT_CONNECTION_KEY = 'database.default';

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * Start up the dependency
     */
    public function boot()
    {
        //  Kick off the management interrogation
        Managed::initialize();

        //******************************************************************************
        //* To be efficient laravel DatabaseManager only creates a connection once and
        //* it happens earlier during the db bootstrap process. So, by now it has
        //* already created a connection using the connection that's set in
        //* database.default config. Therefore, there is no point making changes in that
        //* connection (specified in database.default) config. Rather create a new
        //* connection and insert it into the database.connections array and set the
        //* database.default to this new connection.
        //******************************************************************************

        $_dbConfig = Managed::getDatabaseConfig();

        //  Insert our config into the array and set the default connection
        $_key = md5($_dbConfig['database']);
        $_connections = config(static::DATABASE_ALL_CONNECTIONS_KEY);
        $_connections[$_key] = $_dbConfig;

        config([
            static::DATABASE_ALL_CONNECTIONS_KEY    => $_connections,
            static::DATABASE_DEFAULT_CONNECTION_KEY => $_key,
        ]);
    }

    /** @inheritdoc */
    public function register()
    {
        $this->app->singleton(static::IOC_NAME,
            function ($app) {
                return new ManagedService($app);
            });
    }
}
