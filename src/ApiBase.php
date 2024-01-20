<?php
namespace AdinanCenci\GenericRestApi;

use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientInterface;

use AdinanCenci\Psr18\Client as DefaultClient;
use AdinanCenci\Psr17\ResponseFactory;
use AdinanCenci\Psr17\RequestFactory as DefaultRequestFactory;
use AdinanCenci\Psr17\StreamFactory;

abstract class ApiBase 
{
    /**
     * @var string
     *   The base address for the api.
     */
    protected string $baseUrl = 'https://something.com/';

    /**
     * @var array
     *   Array containig options, implementation specific.
     */
    protected array $options = [
        'timeToLive' => 24 * 60 * 60 * 7, // For how long should GET requests be cached.
    ];

    /**
     * @var Psr\Http\Client\ClientInterface
     *   Http client.
     */
    protected ClientInterface $httpClient;

    /**
     * @var Psr\Http\Message\RequestFactoryInterface
     *   Http request factory.
     */
    protected RequestFactoryInterface $requestFactory;

    /**
     * @var Psr\SimpleCache\CacheInterface|null
     *   Cache.
     */
    protected ?CacheInterface $cache = null;

    /**
     * @param array $options
     *   Implementation specific.
     * @param null|Psr\SimpleCache\CacheInterface $cache
     *   Cache, opcional.
     * @param null|Psr\Http\Client\ClientInterface $httpClient
     *   Optional, the class will use a generic library if not informed.
     * @param Psr\Http\Message\RequestFactoryInterface|null $requestFactory
     *   Optional, the class will use a generic library if not informed.
     */
    public function __construct(
        array $options = [],
        ?CacheInterface $cache = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
    ) 
    {
        $this->options        = $options;
        $this->cache          = $cache;

        $this->httpClient     = $httpClient
            ? $httpClient
            : new DefaultClient(new ResponseFactory(), new StreamFactory());

        $this->requestFactory = $requestFactory 
            ? $requestFactory
            : new DefaultRequestFactory();
    }

    /**
     * Makes a request and returns the response's json body.
     *
     * The response may be cached, it depends on the $options.
     *
     * @param string $endPoint
     * @param array $options
     * @param Psr\Http\Message\ResponseInterface|null $response
     *
     * @return \stdClass|null
     */
    public function getJson(string $endPoint, array $options = [], &$response = null) : ?\stdClass
    {
        $request  = $this->createRequest($endPoint);
        $response = $this->request($request, $options);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 400) {
            $body = (string) $response->getBody();
            return json_decode($body);
        }

        return null;
    }

    /**
     * Makes a request and returns the response.
     *
     * The response may be cached, it depends on the method and the $options.
     *
     * @param Psr\Http\Message\RequestInterface $request
     * @param array $options
     *
     * @return Psr\Http\Message\ResponseInterface
     */
    public function request(RequestInterface $request, array $options = []) : ResponseInterface
    {
        $method = $request->getMethod();

        switch ($method) {
            case 'POST':
                return $this->postRequest($request, $options);
                break;
            default:
                return $this->getRequest($request, $options);
        }
    }

    /**
     * Makes a POST request and returns the response.
     *
     * The response may be cached, it depends on the $options.
     *
     * @param Psr\Http\Message\RequestInterface $request
     * @param array $options
     *
     * @return Psr\Http\Message\ResponseInterface
     */
    public function getRequest(RequestInterface $request, array $options = []) : ResponseInterface
    {
        $cachedResponse = $this->getCachedResponse($request);
        if ($cachedResponse) {
            return $cachedResponse;
        }

        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode == 0) {

        }

        $this->cacheResponse($request, $response, $this->getTimeToLive($options));
        return $response;
    }

    /**
     * Makes a POST request and returns the response.
     *
     * The response is **NOT** cached.
     *
     * @param Psr\Http\Message\RequestInterface $request
     * @param array $options
     *
     * @return Psr\Http\Message\ResponseInterface
     */
    public function postRequest(RequestInterface $request, array $options = []) : ResponseInterface
    {
        $response = $this->httpClient->sendRequest($request);
        return $response;
    }

    protected function getTimeToLive(array $options) : int
    {
        if (! empty($options['timeToLive'])) {
            return (int) $options['timeToLive'];
        }

        if (! empty($this->options['timeToLive'])) {
            return (int) $this->options['timeToLive'];
        }

        return 0;
    }

    /**
     * Get the previously cached response of a request.
     * 
     * @param Psr\Http\Message\RequestInterface $request
     * 
     * @return Psr\Http\Message\ResponseInterface|null
     */
    protected function getCachedResponse(RequestInterface $request) 
    {
        if (!$this->cache) {
            return false;
        }

        $cacheKey = $this->getCacheKey($request);
        $cached   = $this->cache->get($cacheKey, null);

        if ($cached) {
            $cached = $cached->withAddedHeader('cache-hit', 'hit');
        }

        return $cached
            ? $cached
            : null;
    }

    /**
     * Caches the response to a request.
     * 
     * @param Psr\Http\Message\RequestInterface $request
     *   The request used to create the response.
     * @param Psr\Http\Message\ResponseInterface $response
     *   The response to be cached.
     * @param int $timeToLive
     *   How long in seconds the response should remain in cache.
     */
    protected function cacheResponse(RequestInterface $request, ResponseInterface $response, int $timeToLive = 0) : void
    {
        if (!$this->cache) {
            return;
        }

        $cacheKey = $this->getCacheKey($request);

        $this->cache->set($cacheKey, $response, $timeToLive);
    }

    /**
     * Creates an unique key out of a request object.
     *
     * @param Psr\Http\Message\RequestInterface $request
     *  A PSR-7 request.
     *
     * @return string
     *   An unique id for the request.
     */
    protected function getCacheKey(RequestInterface $request) : string
    {
        $uri      = $request->getUri();
        $endpoint = $uri->getPath() . $uri->getQuery();
        $cacheKey = md5($endpoint);

        return $cacheKey;
    }

    /**
     * Bootstrap a request object out of an relative endpoint URL string.
     *
     * It may include a query string.
     *
     * @return Psr\Http\Message\RequestInterface
     */
    protected function createRequest(string $endPoint) : RequestInterface
    {
        $url = $this->getFullUrl($endPoint);
        return $this->requestFactory->createRequest('GET', $url);
    }

    /**
     * Transform a relative URL into an absolute one.
     *
     * @param string $endPoint
     *   A path relative to the API's base URL.
     *
     * @return string
     *   An absolute URL.
     */
    protected function getFullUrl(string $endPoint) : string
    {
        return $this->baseUrl . $endPoint;
    }
}
