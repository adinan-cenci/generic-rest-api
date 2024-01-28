<?php 
use AdinanCenci\FileCache\Cache;
use AdinanCenci\Psr18\Client;

use AdinanCenci\GenericRestApi\Exception\UserError;

//---------------------------------------

require '../vendor/autoload.php';
require './Swapi.php';

//---------------------------------------

$cacheDir     = __DIR__ . '/cache/';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir);
}

//---------------------------------------

$cache = new Cache($cacheDir); // PSR-16
$swapi = new Swapi([], $cache);

//---------------------------------------

$luke = $swapi->getPersonByTheId(1, $response);

$cacheHit = $response->getHeaderLine('cache-hit') ?? 'not';

echo '<h3>Cache hit ?</h3>';
echo $cacheHit;
echo '<br>';
echo '<pre>';
print_r($luke);
echo '</pre>';

//---------------------------------------

try {
    $swapi->trigger404();
} catch (UserError $e) {
    echo $e->getMessage();
}
