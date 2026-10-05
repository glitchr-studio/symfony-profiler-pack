<?php

use Glitchr\ProfilerPack\Tests\App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3).'/vendor/autoload.php';

// PACK: the pack's configuration as JSON ("{}" by default); PACK=off leaves the
// bundle out, to compare with the toolbar as Symfony ships it.
$pack = $_SERVER['PACK'] ?? getenv('PACK') ?: '{}';
$kernel = new Kernel('dev', true, 'off' === $pack ? null : json_decode($pack, true, flags: \JSON_THROW_ON_ERROR));

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
