<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Andreas Würl, Daniele Fognini

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

use Fossology\Lib\Html\HtmlElement;
use Fossology\Lib\Util\Object;

class Highlight extends Object
{
  const MATCH = "M";
  const CHANGED = "MC";
  const ADDED = "MA";
  const DELETED = "MD";
  const SIGNATURE = "S";
  const KEYWORD = "K";

  const COPYRIGHT = "C";
  const URL = "U";
  const EMAIL = "E";
  const UNDEFINED = "any";

  /**
   * @var int
   */
  private $start;
  /**
   * @var int
   */
  private $end;
  /**
   * @var string
   */
  private $type;
  /**
   * @var int
   */
  private $licenseId;
  /**
   * @var int
   */
  private $refStart;
  /**
   * @var int
   */
  private $refEnd;
  /**
   * @var string
   */
  private $infoText;
  /**
   * @var HtmlElement
   */
  private $htmlElement;

  /**
   * @param int $start
   * @param int $end
   * @param string $type
   * @param int $refStart
   * @param int $refEnd
   * @param string $infoText
   * @param null|HtmlElement $htmlElement
   */
  function __construct($start, $end, $type, $refStart=-1, $refEnd=-1, $infoText = "", $htmlElement = null)
  {
    $this->start = $start;
    $this->end = $end;
    $this->type = $type;
    $this->refStart = $refStart;
    $this->refEnd = $refEnd;
    $this->infoText = $infoText;
    $this->htmlElement = $htmlElement;

    $this->licenseId = null;
  }

  /**
   * @return int
   */
  public function getStart()
  {
    return $this->start;
  }

  /**
   * @return int
   */
  public function getEnd()
  {
    return $this->end;
  }

  /**
   * @return string
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * @param $licenseId
   * @return void
   */
  public function setLicenseId($licenseId)
  {
    $this->licenseId = $licenseId;
  }

  /**
   * @return int
   */
  public function getLicenseId()
  {
    return $this->licenseId;
  }

  /**
   * @return boolean
   */
  public function hasLicenseId()
  {
    return $this->licenseId != null;
  }

  /**
   * @return boolean
   */
  public function isPersistent()
  {
    return $this->licenseId == null;
  }


  /**
   * @return int
   */
  public function getRefStart()
  {
    return $this->refStart;
  }

  /**
   * @return int
   */
  public function getRefEnd()
  {
    return $this->refEnd;
  }

  /**
   * @return int
   */
  public function getRefLength()
  {
    return max(0, $this->refEnd - $this->refStart);
  }

  /**
   * @param string $infoText
   */
  public function setInfoText($infoText)
  {
    $this->infoText = $infoText;
  }

  /**
   * @return string
   */
  public function getInfoText()
  {
    return $this->infoText;
  }

  /**
   * @return null|HtmlElement
   */
  public function getHtmlElement()
  {
    /**
     * @var HtmlElement
     */
    $htmlElement = $this->htmlElement;
    return $htmlElement;
  }


  public function __toString()
  {
    return "Highlight(" . $this->start . "-" . $this->end . ", type=" . $this->type . ", id=" . $this->licenseId . ")";
  }

} 