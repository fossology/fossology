<?php
/**
 * SPDX-License-Identifier: GPL-2.0
 * SPDX-FileCopyrightText: Copyright (c) 2021 Orange
 * Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
