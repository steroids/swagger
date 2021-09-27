<?php

define('STEROIDS_ROOT_DIR', realpath(__DIR__ . '/../../..'));
define('YII_ENV', 'test');

$config = require STEROIDS_ROOT_DIR . '/bootstrap.php';
new \steroids\core\base\ConsoleApplication($config);
