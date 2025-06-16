<?php

namespace AdinanCenci\GenericRestApi\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Error extends \Exception
{
    /**
     * The request that caused the exception.
     *
     * @var Psr\Http\Message\RequestInterface
     */
    protected RequestInterface $request;

    /**
     * The response we got.
     *
     * @var Psr\Http\Message\ResponseInterface
     */
    protected ResponseInterface $response;

    /**
     * Constructor.
     *
     * @param string $message
     *   The description of the exception.
     * @param Psr\Http\Message\RequestInterface $request
     *   The request that caused the exception.
     * @param Psr\Http\Message\ResponseInterface $response
     *   The response we got.
     */
    public function __construct(string $message, RequestInterface $request, ResponseInterface $response) 
    {
        $this->message  = $message;
        $this->request  = $request;
        $this->response = $response;
    }

    /**
     * Returns the request that caused the exception.
     *
     * @return Psr\Http\Message\RequestInterface
     *   The request object.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Returns the response we got.
     *
     * @return Psr\Http\Message\ResponseInterface
     *   The response object.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Sets the exception message.
     *
     * @param string
     *   The description of the exception.
     */
    public function setMessage(string $message)
    {
        $this->message = $message;
    }
}
