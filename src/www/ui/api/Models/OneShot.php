<?php
/*
 SPDX-FileCopyrightText: © 2024 Divij Sharma <divijs75@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief OneShot model
 */

namespace Fossology\UI\Api\Models;

use Fossology\Lib\Data\Highlight;

class OneShot
{
  /**
   * @var mixed $data
   */
  private $data;

  /**
   * @var Highlight[] $highlights
   */
  private $highlights;

  /**
   * OneShot constructor.
   *
   * @param mixed $data License string or License array
   * @param Highlight[] $highlights
   */
  public function __construct($data, $highlights)
  {
    $this->data  = $data;
    $this->highlights = $highlights;
  }

  ////// Setters //////

  /**
   * @param mixed $data
   */
  public function setData($data)
  {
    $this->data = $data;
  }

  /**
   * @param Highlight[] $highlights
   */
  public function setHighlights($highlights)
  {
    $this->highlights = $highlights;
  }

  ////// Getters //////

  /**
   * @return mixed $Data
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * @return Highlight[]
   */
  public function getHighlights()
  {
    return $this->highlights;
  }

  /**
   * @return string json
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * @return array
   */
  public function getHighlightsArray()
  {
    $highlightsArray = array_map(function($highlight) {
      return $highlight->getArray();
    }, $this->highlights);
    return $highlightsArray;
  }

  /**
   * Get the OneShot object as associative array
   *
   * @return array
   */
  public function getArray($dataType = 'licenses')
  {
    return [
      $dataType => $this->data,
      'highlights' => $this->getHighlightsArray()
    ];
  }
}
