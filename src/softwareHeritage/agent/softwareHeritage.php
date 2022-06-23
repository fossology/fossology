<?php
/*
 SPDX-FileCopyrightText: © 2019 Sandip Kumar Bhuyan <sandipbhyan@gmail.com>
 Author: Sandip Kumar Bhuyan<sandipbhyan@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Source for software heritage agent
 * @page softwareHeritage SoftwareHeritage agent
 * @tableofcontents
 * @section softwareHeritage About SoftwareHeritage agent
 *
 *
 * @section software_heirtage Agent source
 *   - @link src/softwareHeritage/agent @endlink
 *   - @link src/softwareHeritage/ui @endlink
 */
/**
 * @namespace Fossology\SoftwareHeritage
 * @brief Namespace used by softwareHeritage agent
 */
namespace Fossology\SoftwareHeritage;

include_once(__DIR__ . "/softwareHeritageAgent.php");

$agent = new softwareHeritageAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
