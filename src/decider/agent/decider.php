<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "decider");
define("VERSION", "trunk");

/**
 * \file decider.php
 */

/**
 * include common-cli.php directly, common.php can not include common-cli.php
 * becuase common.php is included before UI_CLI is set
 */

use Fossology\Lib\Agent\Agent;

class DeciderAgent extends Agent
{
  function __construct()
  {
    parent::__construct(AGENT_NAME, VERSION);
  }

  function processUploadId($uploadId)
  {
    $count = $this->dbManager->getSingleRow("SELECT count(*) AS count FROM uploadtree WHERE upload_fk = $1",
    array($uploadId));

    $this->heartbeat($count['count']);

    return true;
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_schedueler_event_loop();
$agent->bail(0);