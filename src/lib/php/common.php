<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * @dir
 * @brief Common library functions for agents based on PHP language
 * \file
 * \brief These are common functions to be used by anyone.
 * @page libphp FOSSology PHP library
 * @tableofcontents
 *
 * @section libphpabout About
 * This is library contains common utility functions for FOSSology agents
 * written in PHP language.
 *
 * The library is modular. Include @link common.php @endlink to include all
 * library functionalities.
 * @section libphpsource Library source
 * - @link src/lib/php @endlink
 */

// setup autoloading
require_once(dirname(dirname(dirname(__FILE__))) . "/vendor/autoload.php");

require_once("common-sysconfig.php");
require_once("fossdash-config.php");

// setup dependency injection
require_once("common-container.php");

require_once("common-scheduler.php");
require_once("common-menu.php");
require_once("common-plugin.php");
require_once("common-folders.php");

require_once("common-projects.php");

require_once("common-dir.php");
require_once("common-parm.php");
require_once("common-repo.php");
require_once("common-license-file.php");
require_once("common-job.php");
require_once("common-agents.php");
require_once("common-active.php");
require_once("common-cache.php");
require_once("common-ui.php");
require_once("common-buckets.php");
require_once("common-pkg.php");
require_once("common-tags.php");
require_once("common-compare.php");
require_once("common-db.php");
require_once("common-auth.php");
require_once("common-perms.php");
require_once("common-users.php");
require_once("common-string.php");
/* Only include the command-line interface functions if it is required. */
global $UI_CLI;
if (! empty($UI_CLI) && ($UI_CLI == 1)) {
  require_once ("common-cli.php");
}
