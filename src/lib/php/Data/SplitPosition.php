<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\Data;

use Fossology\Lib\Util\Object;

class SplitPosition extends Object
{
  const START = 'start';
  const END = 'end';
  const ATOM = 'atom';
  const TYPE = 'type';

  /**
   * @var int
   */
  private $level;
  /**
   * @var string
   */
  private $action;
  /**
   * @var Highlight
   */
  private $highlight;

  /**
   * @param int $level
   * @param string $action
   * @param Highlight $highlight
   */
  function __construct($level, $action, $highlight)
  {
    $this->level = $level;
    $this->action = $action;
    $this->highlight = $highlight;
  }

  /**
   * @return string
   */
  public function getAction()
  {
    return $this->action;
  }

  /**
   * @return \Fossology\Lib\Data\Highlight
   */
  public function getHighlight()
  {
    return $this->highlight;
  }

  /**
   * @return int
   */
  public function getLevel()
  {
    return $this->level;
  }

  function __toString()
  {
    return "SplitPosition(level=" . $this->level . ", " . $this->action . ", " . $this->highlight . ")";
  }

}