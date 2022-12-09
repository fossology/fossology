<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
 Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Source for reso  agent
 * @page reso Reso agent
 * @tableofcontents
 * @section reso About Reso agent
 *
 *
 * @section reso Agent source
 *   - @link src/reso/agent @endlink
 *   - @link src/reso/ui @endlink
 */
/**
 * @namespace Fossology\Reso
 * @brief Namespace used by reso agent
 */
namespace Fossology\Reso;

include_once(__DIR__ . "/ResoAgent.php");

$agent = new ResoAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
