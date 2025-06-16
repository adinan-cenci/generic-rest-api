<?php

namespace AdinanCenci\GenericRestApi;

use AdinanCenci\GenericRestApi\Exception\UserError;
use AdinanCenci\GenericRestApi\Exception\ServerError;
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
     * The base address for the api.
     *
     * @var string
     */
    protected string $baseUrl = 'https://something.com/';

    /**
     * Array containig options, implementation specific.
     *
     * @var array
     */
    protected array $options = [
        'timeToLive' => 24 * 60 * 60 * 7, // For how long should GET requests be cached.
    ];

    /**
     * Http client.
     *
     * @var Psr\Http\Client\ClientInterface
     */
    protected ClientInterface $httpClient;

    /**
     * Http request factory.
     *
     * @var Psr\Http\Message\RequestFactoryInterface
     */
    protected RequestFactoryInterface $requestFactory;

    /**
     * Cache.
     *
     * @var Psr\SimpleCache\CacheInterface|null
     */
    protected ?CacheInterface $cache = null;

    /**
     * Constructor.
     *
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
    ) {
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
     *   Relative path to the API endpoint.
     * @param array $options
     *   Options for the request.
     * @param Psr\Http\Message\ResponseInterface|null $response
     *   The response object, in case we need it.
     *
     * @return \stdClass|null
     *   The decoded json.
     */
    public function getJson(string $endPoint, array $options = [], &$response = null): ?\stdClass
    {
        $request  = $this->createRequest($endPoint);
        $response = $this->request($request, $options);
        $statusCode = $response->getStatusCode();

        $body = (string) $response->getBody();
        return json_decode($body);

        return null;
    }

    /**
     * Makes a request and returns the response.
     *
     * The response may be cached, it depends on the method and the $options.
     *
     * @param Psr\Http\Message\RequestInterface $request
     *   The request object.
     * @param array $options
     *   Options for the request.
     *
     * @return Psr\Http\Message\ResponseInterface
     *   The response object.
     */
    public function request(RequestInterface $request, array $options = []): ResponseInterface
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
     *   The request object.
     * @param array $options
     *   Options for the request.
     *
     * @return Psr\Http\Message\ResponseInterface
     *   The response object.
     */
    public function getRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $cachedResponse = $this->getCachedResponse($request);
        if ($cachedResponse) {
            return $cachedResponse;
        }

        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 && $statusCode < 500) {
            throw new UserError($this->generateExceptionMessage($response), $request, $response);
        } elseif ($statusCode >= 500) {
            throw new ServerError($this->generateExceptionMessage($response), $request, $response);
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
     *   The request object.
     * @param array $options
     *   Options for the request.
     *
     * @return Psr\Http\Message\ResponseInterface
     *   The response object.
     */
    public function postRequest(RequestInterface $request, array $options = []) : ResponseInterface
    {
        $response = $this->httpClient->sendRequest($request);
        return $response;
    }

    /**
     * Given an array, returns how long a cache should live.
     *
     * @param array $options
     *   Options used on a request.
     *
     * @return int
     *   Time to live in seconds.
     */
    protected function getTimeToLive(array $options): int
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
     * Given a request object, returns the previously cached response.
     *
     * A cache system need to be set in first place.
     *
     * @param Psr\Http\Message\RequestInterface $request
     *   The request.
     * @return Psr\Http\Message\ResponseInterface|null|bool
     *   Returns false if there is no cache system set.
     *   Null if the cache is expired or is not there.
     *   Returns the cached response object otherwise.
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
    protected function cacheResponse(RequestInterface $request, ResponseInterface $response, int $timeToLive = 0): void
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
    protected function getCacheKey(RequestInterface $request): string
    {
        $uri      = $request->getUri();
        $endpoint = $uri->getPath() . $uri->getQuery();
        $cacheKey = md5($request->getMethod() . ':' . $endpoint);

        return $cacheKey;
    }

    /**
     * Bootstrap a request object out of an relative endpoint URL string.
     *
     * It may include a query string.
     *
     * @param string $endpoint
     *   The endpoint.
     *
     * @return Psr\Http\Message\RequestInterface
     *   The request object.
     */
    protected function createRequest(string $endpoint): RequestInterface
    {
        $url = $this->getFullUrl($endpoint);
        return $this->requestFactory->createRequest('GET', $url);
    }

    /**
     * Transform a relative URL into an absolute one.
     *
     * @param string $endpoint
     *   A path relative to the API's base URL.
     *
     * @return string
     *   An absolute URL.
     */
    protected function getFullUrl(string $endpoint): string
    {
        return $this->baseUrl . $endpoint;
    }

    /**
     * Given a response object, generates a message for an exception.
     *
     * @return Psr\Http\Message\ResponseInterface
     *   The response object.
     *
     * @return string
     *   Message for the exception.
     */
    protected function generateExceptionMessage(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
