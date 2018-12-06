<?php
/**
 * @file services.php
 * @brief Add the template path of copyright twig templates
 * to twig.loader
 * @see Twig_Loader_Filesystem
 */
global $container;
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
