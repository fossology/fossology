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
    return $this->startOffset + mb_strlen($this->text, 'UTF-8');
  }

  public function getSlice($startOffset, $endOffset = null)
  {
    $adjustedStartOffset = max($startOffset - $this->startOffset, 0);
    if (isset($endOffset)) {
      $adjustedEndOffset = max($endOffset - $this->startOffset, 0);
      return mb_substr($this->text, $adjustedStartOffset,
        max($adjustedEndOffset - $adjustedStartOffset, 0), 'UTF-8');
    } else {
      return mb_substr($this->text, $adjustedStartOffset, null, 'UTF-8');
    }
  }
}
