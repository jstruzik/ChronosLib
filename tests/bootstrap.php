<?php
// Load our autoloader, and add our Test class namespace
$autoloader = require( __DIR__ . '/../vendor/autoload.php' );
$autoloader->add('OneMightyRoar\ChronosLib\Tests', __DIR__);