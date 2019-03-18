<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

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
 * @file decider.php
 * @brief Create new DeciderAgent object and inform scheduler
 */

namespace Fossology\Decider;

use Fossology\Lib\BusinessRules\LicenseMap;

include_once(__DIR__ . "/DeciderAgent.php");
include_once(__DIR__ . "/BulkReuser.php");

$agent = new DeciderAgent(LicenseMap::CONCLUSION);
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);