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


class Analysis
{
  private $bucket;
  private $copyright;
  private $ecc;
  private $keyword;
  private $mime;
  private $monk;
  private $nomos;
  private $package;

  /**
   * Analysis constructor.
   * @param $bucket boolean
   * @param $copyright boolean
   * @param $ecc boolean
   * @param $keyword boolean
   * @param $mime boolean
   * @param $monk boolean
   * @param $nomos boolean
   * @param $package boolean
   */
  public function __construct($bucket = false, $copyright = false, $ecc = false, $keyword = false,
                              $mime = false, $monk = false, $nomos = false, $package = false)
  {
    $this->bucket = $bucket;
    $this->copyright = $copyright;
    $this->ecc = $ecc;
    $this->keyword = $keyword;
    $this->mime = $mime;
    $this->monk = $monk;
    $this->nomos = $nomos;
    $this->package = $package;
  }


}
