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

namespace Fossology\Lib\Dao;

class LicenseViewDao extends DbViewDao
{
  
  static private $verifiedExistanceOfCandidateTable = FALSE;
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
      $columns = array_key_exists('columns', $options) ? $options['columns'] : array('*');
      $gluedColumns = implode(',', $columns);
      $refColumns = ($gluedColumns=='*') ? "$gluedColumns,0 AS group_fk" : $gluedColumns;
      $dbViewQuery .= " UNION SELECT $refColumns FROM ONLY license_ref";
      if(array_key_exists('extraCondition', $options))
      {
        $dbViewQuery .= " AND $options[extraCondition]";
      }
    }
    parent::__construct($dbViewQuery, $dbViewName);
    self::createTableLicenseCandidate();
  }

  static public function createTableLicenseCandidate()
  {
    if (self::$verifiedExistanceOfCandidateTable)
    {
      return;
    }
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');
    if(!$dbManager->existsTable('license_candidate'))
    {
      $dbManager->queryOnce("CREATE TABLE license_candidate (group_fk integer) INHERITS (license_ref)");
    }
    self::$verifiedExistanceOfCandidateTable = TRUE;
  }
  
}