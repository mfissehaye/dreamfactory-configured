<?php
namespace DreamFactory\Core\Rws\Services;

use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Utility\Session;
use Log;
use Config;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;

class RemoteWeb extends BaseRestService implements CachedInterface
{
    use Cacheable;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var array
     */
    protected $headers;
    /**
     * @var array
     */
    protected $parameters;
    /**
     * @type bool
     */
    protected $cacheEnabled = false;
    /**
     * @var string
     */
    protected $cacheQuery;
    /**
     * @var string
     */
    protected $query;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var array
     */
    protected $curlOptions = [];

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new RemoteWebService
     *
     * @param array $settings settings array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($settings)
    {
        parent::__construct($settings);
        $this->autoDispatch = false;
        $this->query = '';
        $this->cacheQuery = '';

        $config = ArrayUtils::get($settings, 'config', []);
        $this->baseUrl = ArrayUtils::get($config, 'base_url');

        // Validate url setup
        if (empty($this->baseUrl)) {
            throw new \InvalidArgumentException('Remote Web Service base url can not be empty.');
        }
        $this->parameters = ArrayUtils::clean(ArrayUtils::get($config, 'parameters', []));
        $this->headers = ArrayUtils::clean(ArrayUtils::get($config, 'headers', []));

        $this->cacheEnabled = ArrayUtils::getBool($config, 'cache_enabled');
        $this->cacheTTL = intval(ArrayUtils::get($config, 'cache_ttl', Config::get('df.default_cache_ttl')));
        $this->cachePrefix = 'service_' . $this->id . ':';
    }

    /**
     * @param      $query
     * @param      $key
     * @param      $name
     * @param      $value
     * @param bool $add_to_query
     * @param bool $add_to_key
     */
    protected static function parseArrayParameter(
        &$query,
        &$key,
        $name,
        $value,
        $add_to_query = true,
        $add_to_key = true
    ){
        if (is_array($value)) {
            foreach ($value as $sub => $subValue) {
                static::parseArrayParameter($query, $cache_key, $name . '[' . $sub . ']', $subValue, $add_to_query,
                    $add_to_key);
            }
        } else {
            Session::replaceLookups($value, true);
            $part = urlencode($name);
            if (!empty($value)) {
                $part .= '=' . urlencode($value);
            }
            if ($add_to_query) {
                if (!empty($query)) {
                    $query .= '&';
                }
                $query .= $part;
            }
            if ($add_to_key) {
                if (!empty($key)) {
                    $key .= '&';
                }
                $key .= $part;
            }
        }
    }

    /**
     * @param $config
     * @param $action
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    protected static function doesActionApply($config, $action)
    {
        $excludeVerbMasks = intval(ArrayUtils::get($config, 'action'));
        $myActionMask = VerbsMask::toNumeric($action);

        return ($excludeVerbMasks & $myActionMask);
    }

    /**
     * @param array  $parameters
     * @param string $action
     * @param string $query
     * @param string $cache_key
     * @param array  $requestQuery
     *
     * @return void
     */
    protected static function buildParameterString(
        $parameters,
        $action,
        &$query,
        &$cache_key,
        $requestQuery
    ){
        // inbound parameters from request to be passed on
        foreach ($requestQuery as $name => $value) {
            $outbound = true;
            $addToCacheKey = true;
            // unless excluded
            if (!empty($parameters)) {
                foreach ($parameters as $param) {
                    if (ArrayUtils::getBool($param, 'exclude')) {
                        if (0 === strcasecmp($name, strval(ArrayUtils::get($param, 'name')))) {
                            if (static::doesActionApply($param, $action)) {
                                $outbound = !ArrayUtils::getBool($param, 'outbound', true);
                                $addToCacheKey = !ArrayUtils::getBool($param, 'cache_key', true);
                            }
                        }
                    }
                }
            }

            static::parseArrayParameter($query, $cache_key, $name, $value, $outbound, $addToCacheKey);
        }

        // DSP additional outbound parameters
        if (!empty($parameters)) {
            foreach ($parameters as $param) {
                if (!ArrayUtils::getBool($param, 'exclude')) {
                    if (static::doesActionApply($param, $action)) {
                        $name = ArrayUtils::get($param, 'name');
                        $value = ArrayUtils::get($param, 'value');
                        $outbound = ArrayUtils::getBool($param, 'outbound', true);
                        $addToCacheKey = ArrayUtils::getBool($param, 'cache_key', true);

                        static::parseArrayParameter($query, $cache_key, $name, $value, $outbound, $addToCacheKey);
                    }
                }
            }
        }
    }

    /**
     * @param array  $headers
     * @param string $action
     * @param array  $options
     *
     * @return void
     */
    protected static function addHeaders($headers, $action, &$options)
    {
        if (null === ArrayUtils::get($options, CURLOPT_HTTPHEADER)) {
            $options[CURLOPT_HTTPHEADER] = [];
        }

        // DSP outbound headers, additional and pass through
        if (!empty($headers)) {
            foreach ($headers as $header) {
                if (static::doesActionApply($header, $action)) {
                    $name = ArrayUtils::get($header, 'name');
                    $value = ArrayUtils::get($header, 'value');
                    if (ArrayUtils::getBool($header, 'pass_from_client')) {
                        // Check for Basic Auth pulled into server variable already
                        if ((0 === strcasecmp($name, 'Authorization')) &&
                            (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
                        ) {
                            $value =
                                'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
                        } else {
                            $phpHeaderName = strtoupper(str_replace(['-', ' '], ['_', '_'], $name));
                            // check for non-standard headers (prefix HTTP_) and standard headers like Content-Type
                            $value =
                                (isset($_SERVER['HTTP_' . $phpHeaderName])) ? $_SERVER['HTTP_' . $phpHeaderName]
                                    : (isset($_SERVER[$phpHeaderName])) ? $_SERVER[$phpHeaderName] : $value;
                        }
                    }
                    Session::replaceLookups($value, true);
                    $options[CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
                }
            }
        }
    }

    /**
     * A chance to pre-process the data.
     *
     * @return mixed|void
     */
    protected function preProcess()
    {
        parent::preProcess();

        $this->checkPermission($this->getRequestedAction(), $this->name);

        //  set outbound parameters
        $this->buildParameterString($this->parameters, $this->action, $this->query, $this->cacheQuery,
            $this->request->getParameters());

        //	set outbound headers
        $this->addHeaders($this->headers, $this->action, $this->curlOptions);
    }

    /**
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @return bool
     */
    protected function processRequest()
    {
        $data = $this->request->getContent();

        $resource = (!empty($this->resourcePath) ? ltrim($this->resourcePath, '/') : null);
        if ($resource) {
            $this->url = rtrim($this->baseUrl, '/') . '/' . $resource;
        } else {
            $this->url = $this->baseUrl;
        }

        if (!empty($this->query)) {
            $splicer = (false === strpos($this->baseUrl, '?')) ? '?' : '&';
            $this->url .= $splicer . $this->query;
        }

        $cacheKey = '';
        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    // build cache_key
                    $cacheKey = $this->action . ':' . $this->name;
                    if ($resource) {
                        $cacheKey .= ':' . $resource;
                    }
                    if (!empty($this->cacheQuery)) {
                        $cacheKey .= ':' . $this->cacheQuery;
                    }
                    $cacheKey = hash('sha256', $cacheKey);

                    if (null !== $result = $this->getFromCache($cacheKey)) {
                        return $result;
                    }
                    break;
            }
        }

        Log::debug('Outbound HTTP request: ' . $this->action . ': ' . $this->url);

        Curl::setDecodeToArray(true);
        $result = Curl::request(
            $this->action,
            $this->url,
            $data,
            $this->curlOptions
        );

        if (false === $result) {
            $error = Curl::getError();
            throw new RestException(ArrayUtils::get($error, 'code', 500), ArrayUtils::get($error, 'message'));
        }

        $status = Curl::getLastHttpCode();
        if ($status >= 300) {
            if (!is_string($result)) {
                $result = json_encode($result);
            }

            throw new RestException($status, $result, $status);
        }

        $contentType = Curl::getInfo('content_type');
        $format = DataFormats::fromMimeType($contentType);

        $response = ResponseFactory::create($result, $format, $status, $contentType);

        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    $this->addToCache($cacheKey, $result);
                    break;
            }
        }

        return $response;
    }

    public function getApiDocModels()
    {
        return [];
    }

    public function getApiDocInfo()
    {
        return [
            'resourcePath' => '/' . $this->name,
            'produces'     => ['application/json', 'application/xml'],
            'consumes'     => ['application/json', 'application/xml'],
            'apis'         => [],
            'models'       => [],
        ];
    }
}