<?php
/*
 Copyright (C) 2014-2018, Siemens AG
 Author: Daniele Fognini, Andreas Würl

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
/**
 * @dir
 * @brief Source for reuser agent
 * @page reuser Reuser agent
 * @tableofcontents
 * @section reuserabout About Reuser agent
 * If you have already uploaded a version of a package and clearing has been
 * done on it. While uploading the next version of it, you can reuse the
 * clearing decisions from the previous upload saving you lot of time.
 *
 * The files from both uploads (new and old) are compared using their hash.
 * If there is a match, the clearing decisions from reused upload file are
 * copied to the new upload file.
 *
 * Reuser also supports enhanced reuse mode (which can be slow). This mode
 * will copy a clearing decision if the two files differ by one line determined
 * by the `diff` tool.
 *
 * Reuser also supports reusing the main license(s) which copies any main
 * license decisions from old upload to new upload.
 *
 * @section reuseractions Supported actions
 * Currently, unified report agent does not support CLI commands and read only
 * from scheduler.
 *
 * @section reusersource Agent source
 *   - @link src/reuser/agent @endlink
 *   - @link src/reuser/ui @endlink
 *   - Functional test cases @link src/reuser/agent_tests/Functional @endlink
 */
/**
 * @namespace Fossology\Reuser
 * @brief Namespace used by reuser agent
 */
namespace Fossology\Reuser;

include_once(__DIR__."/ReuserAgent.php");

$agent = new ReuserAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
