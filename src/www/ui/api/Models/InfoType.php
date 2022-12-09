<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Models;

/**
 * @class InfoType
 * @abstract
 * @brief Different type of infos provided by REST
 */
abstract class InfoType
{
  const ERROR = "ERROR";
  const INFO = "INFO";
}
