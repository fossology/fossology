<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

class LicenseViewProxy extends DbViewProxy
{
  const CANDIDATE_PREFIX = 'candidatePrefix';
  const OPT_COLUMNS = 'columns';
  /** @var int */
  private $groupId;
  /** @var array */
  private $allColumns = array('rf_pk', 'rf_shortname', 'rf_text', 'rf_url', 'rf_add_date', 'rf_copyleft', 'rf_fullname',
             'rf_notes', 'marydone', 'rf_active', 'rf_text_updatable', 'rf_md5', 'rf_detector_type', 'rf_source',
             'group_fk');

  /**
   * @param int $groupId
   * @param array $options
   * @param string $dbViewName
   */
  public function __construct($groupId, $options=array(), $dbViewName='license_all')
  {
    $this->groupId = $groupId;
    if ($groupId == 0) {
      $dbViewQuery = $this->queryOnlyLicenseRef($options);
      parent::__construct($dbViewQuery, $dbViewName);
      return;
    }
    $dbViewQuery = $this->queryLicenseCandidate($options);
    if (! array_key_exists('diff', $options)) {
      $dbViewQuery .= " UNION ".$this->queryOnlyLicenseRef($options);
    }
    parent::__construct($dbViewQuery, $dbViewName);
  }


  private function queryLicenseCandidate($options)
  {
    $columns = array_key_exists(self::OPT_COLUMNS, $options) ? $options[self::OPT_COLUMNS] : $this->allColumns;
    if (array_key_exists(self::CANDIDATE_PREFIX, $options)) {
      $shortnameId = array_search('rf_shortname',$columns);
      if ($shortnameId !== false) {
        $columns[$shortnameId] = "'". pg_escape_string($options[self::CANDIDATE_PREFIX]). '\'||rf_shortname AS rf_shortname';
      }
    }
    $gluedColumns = implode(',', $columns);
    $dbViewQuery = "SELECT $gluedColumns FROM license_candidate WHERE group_fk=$this->groupId";
    if (array_key_exists('extraCondition', $options)) {
      $dbViewQuery .= " AND $options[extraCondition]";
    }
    return $dbViewQuery;
  }

  private function queryOnlyLicenseRef($options)
  {
    $columns = array_key_exists(self::OPT_COLUMNS, $options) ? $options[self::OPT_COLUMNS] : $this->allColumns;
    $groupFkPos = array_search('group_fk',$columns);
    if ($groupFkPos) {
      $columns[$groupFkPos] = '0 AS group_fk';
    }
    $gluedColumns = implode(',', $columns);
    $dbViewQuery = "SELECT $gluedColumns FROM ONLY license_ref";
    if (array_key_exists('extraCondition', $options)) {
      $dbViewQuery .= " WHERE $options[extraCondition]";
    }
    return $dbViewQuery;
  }
}
