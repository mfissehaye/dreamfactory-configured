<?php namespace DreamFactory\Managed\Facades;

use DreamFactory\Managed\Enums\AuditLevels;
use DreamFactory\Managed\Providers\AuditServiceProvider;
use DreamFactory\Managed\Services\AuditingService;
use DreamFactory\Managed\Support\GelfLogger;
use Illuminate\Support\Facades\Facade;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Audit
 *
 * @method static void setHost($host = GelfLogger::DEFAULT_HOST)
 * @method static void setPort($port = GelfLogger::DEFAULT_PORT)
 * @method static AuditingService setMetadata(array $metadata)
 * @method static bool log($data = [], $level = AuditLevels::INFO, $request = null)
 * @method static bool logRequest($instanceId, Request $request, $sessionData = null, $level = AuditLevels::INFO, $facility = AuditingService::DEFAULT_FACILITY)
 * @method static GelfLogger getLogger()
 * @method static AuditingService setLogger(LoggerInterface $logger)
 */
class Audit extends Facade
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return AuditServiceProvider::IOC_NAME;
    }

}
