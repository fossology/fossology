<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Daniele Fognini, Steffen Weber, Andreas Würl

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

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class HighlightDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  private $typeMap;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());

    $this->typeMap = array(
        'M' => Highlight::MATCH,
        'M ' => Highlight::MATCH,
        'M0' => Highlight::MATCH,
        'M+' => Highlight::ADDED,
        'M-' => Highlight::DELETED,
        'MR' => Highlight::CHANGED,
        'L' => Highlight::SIGNATURE,
        'L ' => Highlight::SIGNATURE,
        'K' => Highlight::KEYWORD,
        'K ' => Highlight::KEYWORD,
    );
  }

  /**
   * @param int $uploadTreeId
   * @param int $licenseId
   * @param int $agentId
   * @param null $highlightId
   * @return array
   */ 
  private function getHighlightDiffs($uploadTreeId, $licenseId = null, $agentId = null, $highlightId = null)
  {
    $sql = "SELECT start,len,type,rf_fk,rf_start,rf_len
            FROM license_file
              INNER JOIN highlight ON license_file.fl_pk = highlight.fl_fk
              INNER JOIN uploadtree ON uploadtree.pfile_fk = license_file.pfile_fk
              WHERE uploadtree_pk = $1 AND (type LIKE 'M_' OR type = 'L')";
    $params = array($uploadTreeId);
    $stmt = __METHOD__;
    if (!empty($licenseId))
    {
      $params[] = $licenseId;
      $stmt .= '.License';
      $sql .= " AND license_file.rf_fk=$" . count($params);
    }
    if (!empty($agentId))
    {
      $params[] = $agentId;
      $stmt .= '.Agent';
      $sql .= " AND license_file.agent_fk=$" . count($params);
    }
    if (!empty($highlightId))
    {
      $params[] = $highlightId;
      $stmt .= '.Highlight';
      $sql .= " AND fl_pk=$" . count($params);
    }
    $this->dbManager->prepare($stmt, $sql);
    $result = $this->dbManager->execute($stmt, $params);
    $highlightEntries = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $newHiglight = new Highlight(
          intval($row['start']), intval($row['start'] + $row['len']),
          $this->typeMap[$row['type']],
          intval($row['rf_start']), intval($row['rf_start'] + $row['rf_len']));

      $licenseId = $row['rf_fk'];
      if ($licenseId)
      {
        $newHiglight->setLicenseId($licenseId);
      }
      $highlightEntries[] = $newHiglight;
    }
    $this->dbManager->freeResult($result);
    return $highlightEntries;
  }
  
  /*
   * @param int $uploadTreeId
   */
  private function getHighlightKeywords($uploadTreeId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT start,len
             FROM highlight_keyword
             WHERE pfile_fk = (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $1)";
    $this->dbManager->prepare($stmt, $sql);
    $result = $this->dbManager->execute($stmt, array($uploadTreeId));
    $highlightEntries = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $highlightEntries[] = new Highlight(
          intval($row['start']), intval($row['start'] + $row['len']),
          Highlight::KEYWORD, 0, 0);
    }
    $this->dbManager->freeResult($result);
    return $highlightEntries;
  }

  /*
   * @param int $uploadTreeId
   */
  private function getHighlightBulk($uploadTreeId, $licenseId, $agentId, $highlighId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT license_decision_event_fk,start,len, rf_fk
             FROM highlight_bulk INNER JOIN license_ref_bulk
             ON license_ref_bulk.lrb_pk = highlight_bulk.lrb_fk
             WHERE pfile_fk = (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $1)
             AND rf_fk = $2";
    $params = array($uploadTreeId, $licenseId);
    if (!empty($agentId))
    {
      $stmt .= ".Agent";
      $params[] = $agentId == 2 ? "f" : "t";
      $sql .= " AND license_ref_bulk.removing = $" . count($params);
    }
    if (!empty($highlighId))
    {
      $stmt .= ".Highlight";
      $params[] = $highlighId;
      $sql .= " AND highlight_bulk.license_decision_event_fk = $" . count($params);
    }
    $this->dbManager->prepare($stmt, $sql);
    $result = $this->dbManager->execute($stmt, $params);
    $highlightEntries = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $newHighlight = new Highlight(
          intval($row['start']), intval($row['start'] + $row['len']),
          Highlight::BULK, 0, 0);
      $newHighlight->setLicenseId($row['rf_fk']);
      $highlightEntries[] = $newHighlight;
    }
    $this->dbManager->freeResult($result);
    return $highlightEntries;
  }
  
  /**
   * @param int $item
   * @param int $licenseId
   * @param int $agentId
   * @param null $highlightId
   * @return array
   */ 
  public function getHighlightEntries($item, $licenseId = null, $agentId = null, $highlightId = null){
    $highlightDiffs = $this->getHighlightDiffs($item, $licenseId, $agentId, $highlightId);
    $highlightKeywords = $this->getHighlightKeywords($item);
    $highlightBulk = $this->getHighlightBulk($item, $licenseId, $agentId, $highlightId);
    $highlightEntries = array_merge(array_merge($highlightDiffs,$highlightKeywords),$highlightBulk);
    return $highlightEntries;
  }
}