<?php

use AdinanCenci\GenericRestApi\ApiBase;

class CatApi extends ApiBase
{
    protected string $baseUrl = 'https://api.thecatapi.com/v1/';

    public function getRandom10Cats(&$response = null): array
    {
        return $this->getJson('images/search?limit=10', [], $response);
    }

    public function trigger404()
    {
        return $this->getJson('does-not-exist', [], $response);
    }
}
