<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
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
    $this->empty = true;
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
    $this->empty = false;
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
    return $this->empty;
  }

  /**
   * @param string $text
   * @return string
   */
  protected abstract function renderContentText($text);
}
