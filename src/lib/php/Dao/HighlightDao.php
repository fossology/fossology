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
use Fossology\Lib\Data\Tree\ItemTreeBounds;
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
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $licenseId
   * @param int|array $agentId 
   * @param null $highlightId
   * @return Highlight[]
   */
  public function getHighlightDiffs(ItemTreeBounds $itemTreeBounds, $licenseId = null, $agentId = null, $highlightId = null)
  {
    $params =array($itemTreeBounds->getItemId());
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();

    $sql = "SELECT start,len,type,rf_fk,rf_start,rf_len
            FROM license_file
              INNER JOIN highlight ON license_file.fl_pk = highlight.fl_fk
              INNER JOIN $uploadTreeTableName ut ON ut.pfile_fk = license_file.pfile_fk
              WHERE uploadtree_pk = $1 AND (type LIKE 'M_' OR type = 'L')";

    $stmt = __METHOD__.$uploadTreeTableName;
    if (!empty($licenseId) && empty($highlightId))
    {
      $params[] = $licenseId;
      $stmt .= '.License';
      $sql .= " AND license_file.rf_fk=$" . count($params);
    }
    if (!empty($agentId) && is_array($agentId))
    {
      $params[] = '{' . implode(',', $agentId) . '}';
      $stmt .= '.AnyAgent';
      $sql .= " AND license_file.agent_fk=ANY($" . count($params).")";
    }
    else if (!empty($agentId))
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

  public function getHighlightRegion($licenseMatchId)
  {
    $row = $this->dbManager->getSingleRow(
      "SELECT MIN(start) AS start, MAX(start+len) AS end FROM highlight WHERE fl_fk = $1",
      array($licenseMatchId)
    );
    return false !== $row ? array($row['start'], $row['end']) : array(-1, -1);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return Highlight[]
   */
  public function getHighlightKeywords(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__.$uploadTreeTableName;
    $sql = "SELECT start,len
             FROM highlight_keyword
             WHERE pfile_fk = (SELECT pfile_fk FROM $uploadTreeTableName WHERE uploadtree_pk = $1)";
    $this->dbManager->prepare($stmt, $sql);
    $result = $this->dbManager->execute($stmt, array($itemTreeBounds->getItemId()));
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

  /**
   * @param int $uploadTreeId
   * @param int|null $clearingId
   * @return Highlight[]
   */
  public function getHighlightBulk($uploadTreeId, $clearingId = null)
  {
    $stmt = __METHOD__;
    $sql = "SELECT h.clearing_event_fk, h.start, h.len, ce.rf_fk, rf_text
            FROM clearing_event ce
              INNER JOIN highlight_bulk h ON ce.clearing_event_pk = h.clearing_event_fk
              INNER JOIN license_ref_bulk lrb ON lrb.lrb_pk = h.lrb_fk
            WHERE ce.uploadtree_fk = $1";
    $params = array($uploadTreeId);
    if (!empty($clearingId))
    {
      $stmt .= ".clearingId";
      $params[] = $clearingId;
      $sql .= " AND h.clearing_event_fk = $" . count($params);
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
   * @param ItemTreeBounds $itemTreeBounds
   * @param int|null $licenseId
   * @param int|null $agentId
   * @param int|null $highlightId
   * @param int|null $clearingId
   * @return Highlight[]
   */
  public function getHighlightEntries(ItemTreeBounds $itemTreeBounds, $licenseId = null, $agentId = null, $highlightId = null, $clearingId = null){
    $highlightDiffs = $this->getHighlightDiffs($itemTreeBounds, $licenseId, $agentId, $highlightId);
    $highlightKeywords = $this->getHighlightKeywords($itemTreeBounds);
    $highlightBulk = $this->getHighlightBulk($itemTreeBounds->getItemId(), $clearingId);
    $highlightEntries = array_merge(array_merge($highlightDiffs,$highlightKeywords),$highlightBulk);
    return $highlightEntries;
  }
}
