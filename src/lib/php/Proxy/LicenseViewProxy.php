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
    
    $columns = array_key_exists('columns', $options) ? $options['columns'] : array('*');
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
    if (!array_key_exists('diff', $options))
    {
      $dbViewQuery .= " UNION ".$this->queryOnlyLicenseRef($options);
    }
    parent::__construct($dbViewQuery, $dbViewName);
  }
  
  private function queryOnlyLicenseRef($options){
    $columns = array_key_exists('columns', $options) ? $options['columns'] : array('*');
    $gluedColumns = implode(',', $columns);
    $refColumns = ($gluedColumns=='*') ? "$gluedColumns,0 AS group_fk" : $gluedColumns;
    $dbViewQuery = "SELECT $refColumns FROM ONLY license_ref";
    if(array_key_exists('extraCondition', $options))
    {
      $dbViewQuery .= " AND $options[extraCondition]";
    }
    return $dbViewQuery;
  }
  
}