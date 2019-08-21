<?php
# Copyright 2019
# Author: Vivek Kumar<vvksindia@gmail.com>
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.


namespace Fossology\Spasht;

include_once(__DIR__."/SpashtAgent.php");

$agent = new SpashtAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
