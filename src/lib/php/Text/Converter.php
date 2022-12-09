<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Text;

interface Converter
{
  /**
   * @param string $input
   * @return string
   */
  function convert($input);
}