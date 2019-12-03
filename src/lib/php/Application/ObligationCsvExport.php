<?php
/*
Copyright (C) 2017, Siemens AG

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

/**
 * @file
 * @brief Helper class to export obligations as a CSV
 */

/**
 * @class ObligationCsvExport
 * @brief Helper class to export obligations as a CSV
 */
class ObligationCsvExport
{
  /** @var DbManager $dbManager
   * DB manager to be used */
  protected $dbManager;
  /** @var string $delimiter
   * Delimiter used in the CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Ecnlosure used in the CSV */
  protected $enclosure = '"';

  /**
   * Constructor
   * @param DbManager $dbManager DbManager to be used.
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->obligationMap = $GLOBALS['container']->get('businessrules.obligationmap');
  }

  /**
   * @brief Update the delimiter
   * @param string $delimiter New delimiter to use.
   */
  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  /**
   * @brief Update the enclosure
   * @param string $enclosure New enclosure to use.
   */
  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
  }

  /**
   * @brief Create CSV from the obligations
   * @param int $rf Obligation id to be returned, else set 0 to get all.
   * @return string CSV
   */
  public function createCsv($ob=0)
  {
    $csvarray = array();
    $sql = "SELECT ob_pk,ob_type,ob_topic,ob_text,ob_classification,ob_modifications,ob_comment
            FROM obligation_ref;";
    if ($ob>0) {
      $stmt = __METHOD__.'.ob';
      $sql .= ' WHERE ob_pk=$'.$ob;
      $row = $this->dbManager->getSingleRow($sql,$stmt);
      $vars = $row ? array( $row ) : array();
      $liclist = $this->obligationMap->getLicenseList($ob);
      $candidatelist = $this->obligationMap->getLicenseList($ob, True);
      array_shift($vars);
      array_push($vars,$liclist);
      array_push($vars,$candidatelist);
      $csvarray = $vars;
    } else {
      $stmt = __METHOD__;
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt);
      $vars = $this->dbManager->fetchAll($res);
      $this->dbManager->freeResult($res);

      foreach ($vars as $row) {
        $liclist = $this->obligationMap->getLicenseList($row['ob_pk']);
        $candidatelist = $this->obligationMap->getLicenseList($row['ob_pk'], True);
        array_shift($row);
        array_push($row,$liclist);
        array_push($row,$candidatelist);
        array_push($csvarray,$row);
      }
    }

    $out = fopen('php://output', 'w');
    ob_start();
    $head = array('Type','Obligation or Risk topic','Full Text','Classification','Apply on modified source code','Comment','Associated Licenses','Associated candidate Licenses');
    fputcsv($out, $head, $this->delimiter, $this->enclosure);
    foreach ($csvarray as $row) {
      fputcsv($out, $row, $this->delimiter, $this->enclosure);
    }
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }
}
