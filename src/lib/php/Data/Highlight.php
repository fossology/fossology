<?php
/*
 SPDX-FileCopyrightText: © 2014, 2018 Siemens AG
 Authors: Andreas Würl, Daniele Fognini

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Html\HtmlElement;

class Highlight
{
  const MATCH = "M";
  const CHANGED = "MC";
  const ADDED = "MA";
  const DELETED = "MD";
  const SIGNATURE = "S";
  const KEYWORD = "K";
  const BULK = "B";

  const COPYRIGHT = "C";
  const URL = "U";
  const EMAIL = "E";
  const AUTHOR = "A";
  const IPRA = "I";
  const ECC = "X";
  const KEYWORDOTHERS = "KW";
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
    $this->licenseId = intval($licenseId);
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

  private function hasRef()
  {
    return $this->getRefStart() >= 0;
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
    return $this->htmlElement;
  }

  /**
   * Get Highlight element as associative array
   * @return array
   */
  public function getArray()
  {
    return array(
      "start" => intval($this->start),
      "end" => intval($this->end),
      "type" => $this->type == null ? Highlight::UNDEFINED : $this->type,
      "licenseId" => $this->licenseId,
      "refStart" => $this->refStart == -1 ? 0 : $this->refStart,
      "refEnd" => $this->refEnd == -1 ? 0 : $this->refEnd,
      "infoText" => $this->infoText,
      "htmlElement" => $this->htmlElement
    );
  }

  public function __toString()
  {
    return "Highlight("
      . $this->start . "-" . $this->end
      . ", type=" . $this->type
      . ", id=" . $this->licenseId .
      ($this->hasRef() ? ":". $this->refStart . "-" . $this->refEnd : "")
      .")";
  }
}
