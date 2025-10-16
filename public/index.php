<?php
require_once __DIR__.'/../vendor/autoload.php';
use Educify\App;

$app = new App(__DIR__.'/..');
$app->run();
