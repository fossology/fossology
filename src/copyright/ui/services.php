<?php
global $container;
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
