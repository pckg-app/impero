<?php

/**
 * Simply require production entry point, framework will take care of things.
 */
define('__ROOT__', dirname(dirname(__FILE__)) . '/');
require_once __ROOT__ . 'vendor/pckg/framework/src/production.php';
