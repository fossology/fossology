<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: FSFAP
*/
global $container;
$loader = $container->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
