<?php
/*
 Copyright Siemens AG 2021

 Copying and distribution of this file, with or without modification,
 are permitted in any medium without royalty provided the copyright
 notice and this notice are preserved. This file is offered as-is,
 without any warranty.
 */
global $container;
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
