<?php
/**
 * SPDX-FileCopyrightText: © 2022 Siemens AG
 * SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only
 */

/***************************************************************
 Copyright (C) 2024 FOSSology Team

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
 ***************************************************************/

/**
 * @file textphrase.php
 * @brief Create new TextPhraseAgent object and inform scheduler
**/

include_once(__DIR__ . "/textphrase_agent.php");

$agent = new TextPhraseAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0); 