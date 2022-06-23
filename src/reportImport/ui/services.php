<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
$loader = $GLOBALS['container']->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
