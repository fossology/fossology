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

namespace Fossology\Lib\View;


abstract class PagedResult
{
  const TARGET_CHARSET = "UTF-8";

  /**
   * @var string
   */
  private $text;

  /**
   * @var int
   */
  private $startOffset;

  /**
   * @var int
   */
  private $currentOffset;

  public function __construct($startOffset)
  {
    $this->text = "";
    $this->startOffset = $startOffset;
    $this->currentOffset = $startOffset;
  }

  /**
   * @param string $text
   */
  public function appendMetaText($text)
  {
    $this->text .= $text;
  }

  /**
   * @param string $text
   */
  public function appendContentText($text)
  {
    $this->currentOffset += strlen($text);
    $this->appendMetaText($this->renderContentText($text));
  }

  /**
   * @return int
   */
  public function getStartOffset()
  {
    return $this->startOffset;
  }

  /**
   * @return int
   */
  public function getCurrentOffset()
  {
    return $this->currentOffset;
  }

  /**
   * @return string
   */
  public function getText()
  {
    return $this->text;
  }

  /**
   * return bool
   */
  public function isEmpty()
  {
    return $this->currentOffset === $this->startOffset;
  }

  /**
   * @param string $text
   * @return string
   */
  protected abstract function renderContentText($text);

}