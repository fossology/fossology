<?php
/**
 * @file
 * @brief Add the template path of copyright twig templates
 * to twig.loader
 * @see Twig_Loader_Filesystem
 */
$loader = $GLOBALS['container']->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
