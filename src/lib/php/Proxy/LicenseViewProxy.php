<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Proxy;

class LicenseViewProxy extends DbViewProxy
{
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
    if($groupId==0){
      $dbViewQuery = $this->queryOnlyLicenseRef($options);
      parent::__construct($dbViewQuery, $dbViewName);
      return;
    }
    $dbViewQuery = $this->queryLicenseCandidate($options);
    if (!array_key_exists('diff', $options))
    {
      $dbViewQuery .= " UNION ".$this->queryOnlyLicenseRef($options);
    }
    parent::__construct($dbViewQuery, $dbViewName);
  }

  
  private function queryLicenseCandidate($options)
  {
    $columns = array_key_exists('columns', $options) ? $options['columns'] : $this->allColumns;
    if(array_key_exists('candidatePrefix',$options)){
      $shortnameId = array_search('rf_shortname',$columns);
      if ($shortnameId)
      {
        $columns[$shortnameId] = "'". pg_escape_string($options['candidatePrefix']). '\'||rf_shortname AS rf_shortname';
      }
    }
    $gluedColumns = implode(',', $columns);
    $dbViewQuery = "SELECT $gluedColumns FROM license_candidate WHERE group_fk=$this->groupId";
    if(array_key_exists('extraCondition', $options))
    {
      $dbViewQuery .= " AND $options[extraCondition]";
    }
    return $dbViewQuery;
}
  
  private function queryOnlyLicenseRef($options){
    $columns = array_key_exists('columns', $options) ? $options['columns'] : $this->allColumns;
    $groupFkPos = array_search('group_fk',$columns);
    if($groupFkPos){
      $columns[$groupFkPos] = '0 AS group_fk';
    }
    $gluedColumns = implode(',', $columns);
    $dbViewQuery = "SELECT $gluedColumns FROM ONLY license_ref";
    if(array_key_exists('extraCondition', $options))
    {
      $dbViewQuery .= " WHERE $options[extraCondition]";
    }
    return $dbViewQuery;
  }
  
}