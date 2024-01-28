<?php 
use AdinanCenci\GenericRestApi\ApiBase;

class Swapi extends ApiBase 
{
    protected string $baseUrl = 'https://swapi.dev/api/';

    public function getPersonByTheId(string $personId, &$response = null) : \stdClass
    {
        return $this->getJson("people/$personId/", [], $response);
    }

    public function trigger404() 
    {
        return $this->getJson("does-not-exist", [], $response);
    }
}
