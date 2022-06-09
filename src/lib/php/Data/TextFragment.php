<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class TextFragment
{

  private $startOffset;
  private $text;

  public function __construct($startOffset, $text)
  {

    $this->startOffset = $startOffset;
    $this->text = $text;
  }

  public function getStartOffset()
  {
    return $this->startOffset;
  }

  public function getEndOffset()
  {
    return $this->startOffset + strlen($this->text);
  }

  public function getSlice($startOffset, $endOffset = null)
  {
    $adjustedStartOffset = max($startOffset - $this->startOffset, 0);
    if (isset($endOffset)) {
      $adjustedEndOffset = max($endOffset - $this->startOffset, 0);
      return substr($this->text, $adjustedStartOffset,
        max($adjustedEndOffset - $adjustedStartOffset, 0));
    } else {
      return substr($this->text, $adjustedStartOffset);
    }
  }
}
