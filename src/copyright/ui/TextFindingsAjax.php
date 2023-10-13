<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Agent\Copyright\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\DataTablesUtility;
use Symfony\Component\HttpFoundation\JsonResponse;
use CopyrightHistogram;

/**
 * @class TextFindingsAjax
 * @brief Handles Ajax requests for text findings
 */
class TextFindingsAjax
{
  /** @var string $uploadtree_tablename
   * Upload tree to be used
   */
  private $uploadtree_tablename;
  /** @var DbManager $dbManager
   * DbManager object
   */
  private $dbManager;
  /** @var $uploadDao
   * UploadDao UploadDao object
   */
  private $uploadDao;
  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;
  /** @var DataTablesUtility $dataTablesUtility
   * DataTablesUtility object
   */
  private $dataTablesUtility;

  function __construct($uploadTreeTableName)
  {
    global $container;
    $this->dataTablesUtility = $container->get('utils.data_tables_utility');
    $this->uploadDao = $container->get('dao.upload');
    $this->dbManager = $container->get('db.manager');
    $this->copyrightDao = $container->get('dao.copyright');
    $this->uploadtree_tablename = $uploadTreeTableName;
  }

  /**
   * @brief Handles GET request and create a JSON response
   *
   * Gets the text finding history for given upload and generate
   * a JSONResponse using getTableData()
   * @param string $type The text finding type ('copyright')
   * @param int $upload Upload id to fetch results
   * @param bool $activated True to get activated results, false for disabled
   * @return JsonResponse JSON response for JavaScript
   */
  public function doGetData($type, $upload, $activated = true)
  {
    $item = GetParm("item", PARM_INTEGER);
    $filter = GetParm("filter", PARM_STRING);
    $listPage = $this->getViewName($type);

    list ($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->getTableData(
      $upload, $item, $type, $listPage, $filter, $activated);
    return new JsonResponse(
      array(
        'sEcho' => intval($_GET['sEcho']),
        'aaData' => $aaData,
        'iTotalRecords' => $iTotalRecords,
        'iTotalDisplayRecords' => $iTotalDisplayRecords
      ));
  }

  /**
   * @brief Get the text finding data and fill in expected format
   * @param int $upload Upload id to get results from
   * @param int $item Upload tree id of the item
   * @param string $type The text finding type ('copyright')
   * @param string $listPage Page slug to use
   * @param string $filter Filter data from query
   * @param boolean $activated True to get activated copyrights, else false
   * @return array[][] Array of table data, total records in database, filtered
   *         records
   */
  private function getTableData($upload, $item, $type, $listPage, $filter,
    $activated = true)
  {
    list ($rows, $iTotalDisplayRecords, $iTotalRecords) = $this->getTextFindings(
      $upload, $item, $type, $this->uploadtree_tablename, $filter, $activated);
    $aaData = array();
    if (! empty($rows)) {
      $rw = $this->uploadDao->isEditable($upload, Auth::getGroupId());
      foreach ($rows as $row) {
        $aaData[] = $this->fillTableRow($row, $upload, $item, $type, $listPage,
          $activated, $rw);
      }
    }

    return array(
      $aaData,
      $iTotalRecords,
      $iTotalDisplayRecords
    );
  }

  /**
   * @brief Get results from database and format for JSON
   * @param int $upload_pk Upload id to get results from
   * @param int $item Upload tree id of the item
   * @param string $type The text finding type ('copyright')
   * @param string $uploadTreeTableName Upload tree table to use
   * @param string $filter Filter data from query
   * @param boolean $activated True to get activated copyrights, else false
   * @return array[][] Array of table records, filtered records, total records
   */
  protected function getTextFindings($upload_pk, $item, $type,
    $uploadTreeTableName, $filter, $activated = true)
  {
    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);

    $tableName = $this->getTableName($type);
    $orderString = $this->getOrderString();

    list ($left, $right) = $this->uploadDao->getLeftAndRight($item,
      $uploadTreeTableName);

    if ($filter == "") {
      $filter = "none";
    }

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND UT.upload_fk = $upload_pk ";
    }

    $join = "";
    $filterQuery = "";
    if ($filter == "nolic") {
      $noLicStr = "No_license_found";
      $voidLicStr = "Void";
      $join = " INNER JOIN license_file AS LF on cp.pfile_fk = LF.pfile_fk ";
      $filterQuery = " AND LF.rf_fk IN (" . "SELECT rf_pk FROM license_ref " .
        "WHERE rf_shortname IN ('$noLicStr','$voidLicStr')) ";
    }

    $params = array(
      $left,
      $right
    );

    $filterParms = $params;
    $searchFilter = $this->addSearchFilter($filterParms);
    $unorderedQuery = "FROM $tableName AS cp " .
      "INNER JOIN $uploadTreeTableName AS UT ON cp.pfile_fk = UT.pfile_fk " .
      $join . "WHERE cp.textfinding != '' " .
      "AND ( UT.lft BETWEEN  $1 AND  $2 ) " . "AND cp.is_enabled = " .
      ($activated ? 'true' : 'false') . $sql_upload;
    $totalFilter = $filterQuery . " " . $searchFilter;

    $grouping = " GROUP BY hash ";

    $countQuery = "SELECT count(*) FROM (SELECT hash $unorderedQuery $totalFilter $grouping) as K";
    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow($countQuery,
      $filterParms,
      __METHOD__ . $tableName . ".count" . ($activated ? '' : '_deactivated'));
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $countAllQuery = "SELECT count(*) FROM (SELECT hash $unorderedQuery$grouping) as K";
    $iTotalRecordsRow = $this->dbManager->getSingleRow($countAllQuery, $params,
      __METHOD__, $tableName . "count.all" . ($activated ? '' : '_deactivated'));
    $iTotalRecords = $iTotalRecordsRow['count'];

    $range = "";
    $filterParms[] = $offset;
    $range .= ' OFFSET $' . count($filterParms);
    $filterParms[] = $limit;
    $range .= ' LIMIT $' . count($filterParms);

    $sql = "SELECT textfinding, hash, count(*) as textfinding_count " .
      $unorderedQuery . $totalFilter .
      " GROUP BY textfinding, hash " . $orderString .
      $range;
    $statement = __METHOD__ . $filter . $tableName . $uploadTreeTableName .
      ($activated ? '' : '_deactivated');
    $this->dbManager->prepare($statement, $sql);
    $result = $this->dbManager->execute($statement, $filterParms);
    $rows = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);

    return array(
      $rows,
      $iTotalDisplayRecords,
      $iTotalRecords
    );
  }

  /**
   * @brief Create sorting string for database query
   * @return string Sorting string
   */
  private function getOrderString()
  {
    $columnNamesInDatabase = array(
      'textfinding_count',
      'textfinding'
    );

    $defaultOrder = CopyrightHistogram::returnSortOrder();

    return $this->dataTablesUtility->getSortingString($_GET,
      $columnNamesInDatabase, $defaultOrder);
  }

  /**
   * @brief Add filter on content
   * @param[out] array $filterParams Parameters list for database query
   * @return string Filter string for query
   */
  private function addSearchFilter(&$filterParams)
  {
    $searchPattern = GetParm('sSearch', PARM_STRING);
    if (empty($searchPattern)) {
      return '';
    }
    $filterParams[] = "%$searchPattern%";
    return ' AND CP.content ilike $' . count($filterParams) . ' ';
  }

  /**
   * @brief Helper to create action column for results
   * @param string $hash   Unique hash of the decision
   * @param int    $upload Upload id
   * @param string $type   The text finding type ('copyright')
   * @param boolean $activated True if content is activated, else false
   * @param boolean $rw true if content is editable
   * @return string
   */
  private function getTableRowAction($hash, $upload, $type, $activated = true,
    $rw = true)
  {
    $ajaxType = $this->getDecisionTypeName($type);
    if ($rw) {
      $act = "<img";
      if (! $activated) {
        $act .= " hidden='true'";
      }
      $act .= " id='deleteHashDecision$ajaxType$hash' " .
        "onClick='event.preventDefault();deleteHashDecision(\"$hash\",$upload,\"" .
        $ajaxType . "\");' class=\"delete\" src=\"images/space_16.png\">";
      $act .= "<span";
      if ($activated) {
        $act .= " hidden='true'";
      }
      $act .= " id='undoDeleteHashDecision$ajaxType$hash'> " .
        "deactivated [<a href=\"#\" class='undo$type' " .
        "onClick='event.preventDefault();undoHashDecision(\"$hash\",$upload,\"" .
        $ajaxType . "\");return false;'>Undo</a>]</span>";
      return $act;
    }
    if (! $activated) {
      return "deactivated";
    }
    return "";
  }

  /**
   * @brief Fill table content for JSON response
   * @param array $row Result row from database
   * @param int $upload Upload id
   * @param int $item Upload tree id of the item
   * @param string $type The text finding type ('copyright')
   * @param string $listPage Page slug
   * @param boolean $activated True to get activated results, false otherwise
   * @return string[]
   * @internal param boolean $normalizeString
   */
  private function fillTableRow($row, $upload, $item, $type, $listPage,
    $activated = true, $rw = true)
  {
    $hash = $row['hash'];
    $sql = "SELECT pfile_fk FROM " . $this->getTableName($type) .
      " WHERE hash = $1;";
    $statement = __METHOD__ . ".getPfiles";
    $decisions = $this->dbManager->getRows($sql, [$hash], $statement);
    $pfileIds = [];
    foreach ($decisions as $decision) {
      $pfileIds[] = $decision['pfile_fk'];
    }
    $output = array(
      'DT_RowId' => $this->getDecisionTypeName($type) . ",$hash"
    );

    $link = "<a href='";
    $link .= Traceback_uri();
    $link .= "?mod=$listPage&agent=-1&item=$item" .
      "&hash=$hash&type=$type&filter=all";
    $link .= "'>". intval($row['textfinding_count']) . "</a>";
    $output['0'] = $link;
    $output['1'] = convertToUTF8($row['textfinding']);
    $output['2'] = $this->getTableRowAction($hash, $upload, $type, $activated,
      $rw);
    if ($rw && $activated) {
      $output['3'] = "<input type='checkbox' class='deleteBySelect$type' " .
        "id='deleteBySelectfinding$hash' value='$hash,$upload," .
        $this->getDecisionTypeName($type) . "'>";
    } else {
      $output['3'] = "<input type='checkbox' class='undoBySelect$type' " .
        "id='undoBySelectfinding$hash' value='$hash,$upload," .
        $this->getDecisionTypeName($type) . "'>";
    }
    return $output;
  }

  /**
   * @brief Get table name based on decision type
   *
   * - copyFindings => copyright_decision
   * @param string $type Result type
   * @return string Table name
   */
  private function getTableName($type)
  {
    return "copyright_decision";
  }

  /**
   * @brief Get type name for ajax calls based
   *
   * - copyFindings => copyright
   * @param string $type Result type
   * @return string Table name
   */
  private function getDecisionTypeName($type)
  {
    return "copyright";
  }

  /**
   * @brief Get name of view for links
   *
   * - copyFindings => copyright-view
   * @param string $type Result type
   * @return string View name
   */
  private function getViewName($type)
  {
    return "copyright-list";
  }
}
