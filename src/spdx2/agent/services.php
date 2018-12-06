<?php
/*
 Copyright Siemens AG 2015

 Copying and distribution of this file, with or without modification,
 are permitted in any medium without royalty provided the copyright
 notice and this notice are preserved. This file is offered as-is,
 without any warranty.
 */
/**
 * @file
 * @brief Add the template path of copyright twig templates
 * to twig.loader
 * @see Twig_Loader_Filesystem
 */
global $container;
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
