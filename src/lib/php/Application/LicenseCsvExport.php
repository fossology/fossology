<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;

class LicenseCsvExport {
  /** @var DbManager */
  protected $dbManager;
  /** @var string */
  protected $delimiter = ',';
  /** @var string */
  protected $enclosure = '"';

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }
  
  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
  }
  
  /**
   * @param int $rf
   * @return string csv
   */
  public function createCsv($rf=0)
  {
    $sql = "SELECT rf.rf_shortname,rf.rf_fullname,rf.rf_text,rc.rf_shortname parent_shortname,rr.rf_shortname report_shortname,rf.rf_url,rf.rf_notes,rf.rf_source
            FROM license_ref rf
              LEFT JOIN license_map mc ON mc.rf_fk=rf.rf_pk AND mc.usage=$2
              LEFT JOIN license_ref rc ON mc.rf_parent=rc.rf_pk
              LEFT JOIN license_map mr ON mr.rf_fk=rf.rf_pk AND mr.usage=$3
              LEFT JOIN license_ref rr ON mr.rf_parent=rr.rf_pk
            WHERE rf.rf_detector_type=$1";
    $param = array($userDetected=1,LicenseMap::CONCLUSION,LicenseMap::REPORT);
    if ($rf>0)
    {
      $stmt = __METHOD__.'.rf';
      $param[] = $rf;
      $sql .= ' AND rf.rf_pk=$'.count($param);
      $row = $this->dbManager->getSingleRow($sql,$param,$stmt);
      $vars = $row ? array( $row ) : array();
    }
    else
    {
      $stmt = __METHOD__;
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,$param);
      $vars = $this->dbManager->fetchAll( $res );
      $this->dbManager->freeResult($res);
    }
    
    $out = fopen('php://output', 'w');
    ob_start();
    $head = array('shortname','fullname','text','parent_shortname','report_shortname','url','notes','source');
    fputcsv($out, $head, $this->delimiter, $this->enclosure);
    foreach($vars as $row)
    {
      fputcsv($out, $row, $this->delimiter, $this->enclosure);
    }
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }

} 