<?php
/*
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Conf model
 */

namespace Fossology\UI\Api\Models;

use Fossology\Lib\Data\Package\ComponentType;


/**
 * @class Conf
 * @brief Conf model to contain general error and return values
 */
class Conf
{
  /**
   * @var object $data
   * data for conf
   */
  private $data;

  /**
   * @var array $keyMap
   * key mapping for conf
   */
  const KEY_MAP = [
    'reviewed' => "ri_reviewed",
    'footer' => "ri_footer",
    'reportRel' => "ri_report_rel",
    'community' => "ri_community",
    'component' => "ri_component",
    'version' => "ri_version",
    'releaseDate' => "ri_release_date",
    'sw360Link' => "ri_sw360_link",
    'componentType' => "ri_component_type",
    'componentId' => "ri_component_id",
    'generalAssesment' => "ri_general_assesment",
    'gaAdditional' => "ri_ga_additional",
    'gaRisk' => "ri_ga_risk",
    'gaCheckbox' => "ri_ga_checkbox_selection",
    'spdxSelection' => "ri_spdx_selection",
    'excludedObligations' => "ri_excluded_obligations",
    'department' => "ri_department",
    'depNotes' => "ri_depnotes",
    'exportNotes' => "ri_exportnotes",
    'copyrightNotes' => "ri_copyrightnotes",
    'unifiedColumns' => "ri_unifiedcolumns",
    'globalDecision' => "ri_globaldecision",
  ];

  /**
   * conf constructor.
   * @param object $data
   */
  public function __construct($data)
  {
    $this->data = $data;
  }

  ////// Getters //////

  /**
   * Get the info as JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get info as associative array
   * @return array
   */
  public function getArray()
  {
    return array(
      'reviewed' => $this->data["ri_reviewed"],
      'footer' => $this->data["ri_footer"],
      'reportRel' => $this->data["ri_report_rel"],
      'community' => $this->data["ri_community"],
      'component' => $this->data["ri_component"],
      'version' => $this->data["ri_version"],
      'releaseDate' => $this->data["ri_release_date"],
      'sw360Link' => $this->data["ri_sw360_link"],
      'componentType' => ComponentType::TYPE_MAP[$this->data["ri_component_type"]],
      'componentId' => $this->data["ri_component_id"],
      'generalAssesment' => $this->data["ri_general_assesment"],
      'gaAdditional' => $this->data["ri_ga_additional"],
      'gaRisk' => $this->data["ri_ga_risk"],
      'gaCheckbox' => $this->data["ri_ga_checkbox_selection"],
      'spdxSelection' => $this->data["ri_spdx_selection"],
      'excludedObligations' => json_decode($this->data["ri_excluded_obligations"], TRUE),
      'department' => $this->data["ri_department"],
      'depNotes' => $this->data["ri_depnotes"],
      'exportNotes' => $this->data["ri_exportnotes"],
      'copyrightNotes' => $this->data["ri_copyrightnotes"],
      'unifiedColumns' => json_decode($this->data["ri_unifiedcolumns"], TRUE),
      'globalDecision' => boolval($this->data["ri_globaldecision"]),
    );
  }

  /**
   * Get key name from parameter
   * @return array
   */
  public function getKeyValue()
  {
    return self::KEY_MAP[$this->data];
  }

  /**
   * Check key exists or not
   * @return array
   */
  public function doesKeyExist()
  {
    if (in_array($this->data, self::KEY_MAP)) {
      return true;
    } else {
      return false;
    }
  }
}
