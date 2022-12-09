<?php
/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file decider.php
 * @brief Create new DeciderAgent object and inform scheduler
**/

namespace Fossology\Decider;

use Fossology\Lib\BusinessRules\LicenseMap;

include_once(__DIR__ . "/DeciderAgent.php");
include_once(__DIR__ . "/BulkReuser.php");

$agent = new DeciderAgent(LicenseMap::CONCLUSION);
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);