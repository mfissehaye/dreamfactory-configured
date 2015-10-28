<?php namespace DreamFactory\Managed\Services;

use DreamFactory\Library\Utility\Disk;
use DreamFactory\Managed\Contracts\ProvidesManagedConfig;
use DreamFactory\Managed\Contracts\ProvidesManagedStorage;
use DreamFactory\Managed\Enums\ManagedDefaults;
use DreamFactory\Managed\Support\Managed;
use Illuminate\Contracts\Foundation\Application;

/**
 * A service that returns various configuration data that are common across managed
 * and unmanaged instances. See the VirtualConfigProvider contract
 */
class ManagedService implements ProvidesManagedConfig, ProvidesManagedStorage
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Application No underscore so it matches ServiceProvider class...
     */
    protected $app;
    /**
     * @type string
     */
    protected $privatePathName;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app = null)
    {
        $this->app = $app;
    }

    /**
     * Perform any service initialization
     */
    public function boot()
    {
        Managed::initialize();

        $this->privatePathName = Disk::segment(config('df.private-path-name',
            ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME));
    }

    /** @inheritdoc */
    public function getStoragePath($append = null)
    {
        return Managed::getStoragePath($append);
    }

    /** @inheritdoc */
    public function getPrivatePath($append = null)
    {
        return Managed::getPrivatePath($append);
    }

    /** @inheritdoc */
    public function getOwnerPrivatePath($append = null)
    {
        return Managed::getOwnerPrivatePath($append);
    }

    /** @inheritdoc */
    public function getSnapshotPath()
    {
        return $this->getOwnerPrivatePath(config('df.snapshot-path-name',
            ManagedDefaults::SNAPSHOT_PATH_NAME));
    }

    /** @inheritdoc */
    public function getPrivatePathName()
    {
        return $this->privatePathName;
    }

    /** @inheritdoc */
    public function getLogPath()
    {
        return Managed::getLogPath();
    }

    /** @inheritdoc */
    public function getLogFile($name = null)
    {
        return Managed::getLogFile($name);
    }

    /** @inheritdoc */
    public function getDatabaseConfig()
    {
        return Managed::getDatabaseConfig();
    }

    /** @inheritdoc */
    public function getCachePath()
    {
        return Managed::getCachePath();
    }
}