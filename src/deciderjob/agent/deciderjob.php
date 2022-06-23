<?php
/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\DeciderJob;

use Fossology\Lib\BusinessRules\LicenseMap;

include_once(__DIR__ . "/DeciderJobAgent.php");

$agent = new DeciderJobAgent(LicenseMap::CONCLUSION);
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);