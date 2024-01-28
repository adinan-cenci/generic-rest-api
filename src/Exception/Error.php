<?php 
namespace AdinanCenci\GenericRestApi\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Error extends \Exception 
{
    protected RequestInterface $request;
    protected ResponseInterface $response;

    public function __construct(string $message, RequestInterface $request, ResponseInterface $response) 
    {
        $this->message  = $message;
        $this->request  = $request;
        $this->response = $response;
    }

    public function getRequest() : RequestInterface
    {
        return $this->request;
    }

    public function getResponse() : ResponseInterface
    {
        return $this->response;
    }

    public function setMessage(string $message) 
    {
        $this->message = $message;
    }
}
