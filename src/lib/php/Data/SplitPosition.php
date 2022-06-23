<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class SplitPosition
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
