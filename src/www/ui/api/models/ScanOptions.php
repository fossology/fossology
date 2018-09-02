<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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


namespace www\ui\api\models;


class ScanOptions
{
  private $analysis;
  private $reuse;
  private $decider;

  /**
   * ScanOptions constructor.
   * @param $analysis Analysis
   * @param $reuse integer
   * @param $decider Decider
   */
  public function __construct($analysis, $reuse, $decider)
  {
    $this->analysis = $analysis;
    $this->reuse = $reuse;
    $this->decider = $decider;
  }

  /**
   * Get ScanOptions elements as array
   * @return array
   */
  public function getArray()
  {
    return [
      "analysis"  => $this->analysis,
      "reuse"     => $this->reuse,
      "decide"    => $this->decider
    ];
  }
}
