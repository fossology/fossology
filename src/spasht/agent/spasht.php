<?php
/*
 SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
 Author: Vivek Kumar <vvksindia@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Spasht;

include_once(__DIR__."/SpashtAgent.php");

$agent = new SpashtAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
