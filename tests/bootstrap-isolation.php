<?php

// Bootstrap de PHPUnit puro: no carga el TestCase Pest/JMac heredado.
require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/CreatesApplication.php';
require_once __DIR__.'/Isolation/LegacyRoutesSecurityTest.php';
