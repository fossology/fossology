<?php
/*
 Copyright (C) 2019
 Author: Sandip Kumar Bhuyan<sandipbhyan@gmail.com>

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
 * @namespace Fossology\Reuser
 * @brief Namespace used by reuser agent
 */
namespace Fossology\SoftwareHeritage;

include_once(__DIR__ . "/softwareHeritageAgent.php");

$agent = new softwareHeritageAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
