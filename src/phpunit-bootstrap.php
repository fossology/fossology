<?php

/*
Copyright Siemens AG 2014

Copying and distribution of this file, with or without modification,
are permitted in any medium without royalty provided the copyright
notice and this notice are preserved.  This file is offered as-is,
without any warranty.
*/

namespace TestSupport;

/**
 * @var \Composer\Autoload\Classloader
 */
$classLoader = require __DIR__ . '/vendor/autoload.php';

$hamcrestUtilPath = $classLoader->findFile('\Hamcrest\Util');

require dirname($hamcrestUtilPath) . '/../Hamcrest.php';

