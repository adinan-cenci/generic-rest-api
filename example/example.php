<?php

use AdinanCenci\FileCache\Cache;
use AdinanCenci\Psr18\Client;
use AdinanCenci\GenericRestApi\Exception\UserError;

//---------------------------------------

if (!file_exists('../vendor/autoload.php')) {
    echo '<h1>Autoload file not found</h1>';
    die();
}

require '../vendor/autoload.php';
require './CatApi.php';

//---------------------------------------

$cacheDir     = __DIR__ . '/cache/';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir);
}

//---------------------------------------

$cache  = new Cache($cacheDir); // PSR-16
$catApi = new CatApi([], $cache);

//---------------------------------------

$cats = $catApi->getRandom10Cats($response);

$cacheHit = $response->getHeaderLine('cache-hit') ?? 'not';

echo '<h3>Cache hit ?</h3>';
echo $cacheHit;
echo '<br>';
echo '<pre>';
print_r($cats);
echo '</pre>';

//---------------------------------------

try {
    $catApi->trigger404();
} catch (UserError $e) {
    echo $e->getMessage() . "\n\n";
}
