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

class TextFragment extends Object
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
    if (isset($endOffset))
    {
      $adjustedEndOffset = max($endOffset - $this->startOffset, 0);
      return substr($this->text, $adjustedStartOffset, max($adjustedEndOffset - $adjustedStartOffset, 0));
    } else
    {
      return substr($this->text, $adjustedStartOffset);
    }
  }

} 