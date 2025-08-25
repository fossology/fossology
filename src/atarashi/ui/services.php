<?php
/*
 SPDX-FileCopyrightText: © 2025 Rajul Jha <rajuljha49@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Add the template path of decider twig templates
 * to twig.loader
 * @see Twig_Loader_Filesystem
 */
$loader = $GLOBALS['container']->get('twig.loader');
$loader->addPath(dirname(__FILE__).'/template');
