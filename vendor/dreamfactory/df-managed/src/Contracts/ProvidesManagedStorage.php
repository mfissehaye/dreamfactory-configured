<?php namespace DreamFactory\Managed\Contracts;

interface ProvidesManagedStorage
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Returns the instance's private cache path
     *
     * @return string
     */
    public function getCachePath();

    /**
     * Returns the name of the "private-path" directory. Usually this is ".private"
     *
     * @return string
     */
    public function getPrivatePathName();

    /**
     * @param string|null $append If supplied, added to the end of the path
     *
     * @return string
     */
    public function getOwnerPrivatePath($append = null);

    /**
     * Returns the absolute path to an instance's private path/area
     *
     * @param string|null $append If supplied, added to the end of the path
     *
     * @return string
     */
    public function getPrivatePath($append = null);

    /**
     * Returns the instance's private path, relative to storage-path
     *
     * @return string
     */
    public function getSnapshotPath();

    /**
     * Returns the absolute path to an instance's storage
     *
     * @param string|null $append If supplied, added to the end of the path
     *
     * @return string
     */
    public function getStoragePath($append = null);

}
