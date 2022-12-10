<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Decision Importer agent
 */
namespace Fossology\DecisionImporter;

include_once(__DIR__ . "/DecisionImporter.php");

$agent = new DecisionImporter();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
