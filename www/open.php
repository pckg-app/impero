<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use DebugBar\StandardDebugBar;
use DebugBar\Storage\FileStorage;

require_once "../vendor/autoload.php";

$debugbar = new StandardDebugBar();
$debugbar->setStorage(new FileStorage('/tmp/debugbar_storage'));

$openHandler = new DebugBar\OpenHandler($debugbar);
$openHandler->handle();