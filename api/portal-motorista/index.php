<?php
$rootPath = dirname(__DIR__, 2);
chdir($rootPath);
require_once $rootPath . '/init.php';

new TSession;

$kernel = new PortalMotoristaApiKernel();
$kernel->handle();