<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/


use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\DataTablesUtility;

define("TITLE_copyrightHistogramProcessPost", _("Private: Browse post"));

class CopyrightHistogramProcessPost extends FO_Plugin
{
  protected $listPage;
  /**
   * @var string
   */
  private $uploadtree_tablename;

  /**
   * @var DbManager
   */
  private $dbManager;


  /**
   * @var UploadDao
   */
  private $uploadDao;

  /** @var DataTablesUtility $dataTablesUtility */
  private $dataTablesUtility;

  function __construct()
  {
    $this->Name = "ajax-copyright-hist";
    $this->Title = TITLE_copyrightHistogramProcessPost;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();
    global $container;
    $this->dataTablesUtility = $container->get('utils.data_tables_utility');
    $this->uploadDao = $container->get('dao.upload');
    $this->dbManager = $container->get('db.manager');
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {

    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }

    $action = GetParm("action", PARM_STRING);

    switch($action)
    {
      case "getData":
         return $this->doGetData();
      case "update":
         return $this->doUpdate();
      case "delete":
         return $this->doDelete();
    }

  }

  /**
   * @param $row
   * @param $uploadTreeId
   * @param $upload
   * @param $agentId
   * @param $type
   * @param string $filter
   * @internal param bool $normalizeString
   * @return array
   */
  private function fillTableRow($row, $uploadTreeId, $upload, $agentId, $type,$listPage, $filter = "")
  {
    $output = array();

    $output['DT_RowId'] = $upload . "," . $uploadTreeId . "," . $row['hash']. "," . $type;

    $hash = $row['hash'];

    $link = "<a href='";
    $link .= Traceback_uri();
    $URLargs = "?mod=".$listPage."&agent=$agentId&item=$uploadTreeId&hash=$hash&type=$type";
    if (!empty($filter)) $URLargs .= "&filter=$filter";
    $link .= $URLargs . "'>" . $row['copyright_count'] . "</a>";
    $output['0'] = $link;

    if($type == 'url') {
      $output ['1'] = htmlentities($row['content']);
    }else {
      $output ['1'] = $row['content'];
    }

   // does not work: $output ['1'] = iconv(mb_detect_encoding($row['content'], mb_detect_order(), true), "UTF-8", $row['content']);

    $output ['2'] = "<a id='delete$type$hash' onClick='delete$type($upload,$uploadTreeId,\"$hash\",\"$type\");' href='javascript:;'><img src=\"images/icons/close_16.png\">delete</a><span hidden='true' id='update$type$hash'></span>";
    return $output;
  }


  private function GetTableData($upload, $item, $agent_pk, $type,$listPage, $filter)
  {
    list ($rows, $iTotalDisplayRecords, $iTotalRecords) = $this->getCopyrights($upload, $item, $this->uploadtree_tablename, $agent_pk, $type, $filter);
    $aaData = array();
    if (!empty($rows))
    {
      foreach ($rows as $row)
      {
        $aaData [] = $this->fillTableRow($row, $item, $upload, $agent_pk, $type,$listPage, $filter);
      }
    }

    return array($aaData, $iTotalRecords, $iTotalDisplayRecords);

  }


  private function getOrderString()
  {

    $columnNamesInDatabase = array('copyright_count', 'content');

    $defaultOrder = CopyrightHistogram::returnSortOrder();

    $orderString = $this->dataTablesUtility->getSortingString($_GET, $columnNamesInDatabase, $defaultOrder);

    return $orderString;
  }

  private function addSearchFilter(&$filterParams)
  {
    $searchPattern = GetParm('sSearch', PARM_STRING);
    if (empty($searchPattern))
    {
      return '';
    }
    $filterParams[] = "%$searchPattern%";
    return ' AND CP.content ilike $'.count($filterParams).' ';
  }


  public function getCopyrights($upload_pk, $Uploadtree_pk, $uploadTreeTableName, $Agent_pk, $type, $filter)
  {
    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);

    list($tableName, $filter) = $this->getTableNameAndFilter($type, $filter);

    $orderString = $this->getOrderString();

    list($left, $right) = $this->uploadDao->getLeftAndRight($Uploadtree_pk, $uploadTreeTableName);

    //! Set the default to none
    if ($filter == "") $filter = "none";

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName)
    {
      $sql_upload = " AND UT.upload_fk=$upload_pk ";
    }

    $join = "";
    $filterQuery = "";
    if ($type == "statement")
    {
      if ($filter == "legal")
      {
        $Copyright = "Copyright";
        $filterQuery = " AND CP.content ILIKE ('$Copyright%') ";
      } else if ($filter == "nolics")
      {
        $NoLicStr = "No_license_found";
        $VoidLicStr = "Void";
        $join = " INNER JOIN license_file AS LF on  CP.pfile_fk =LF.pfile_fk ";
        $filterQuery = " AND LF.rf_fk IN (select rf_pk from license_ref where rf_shortname IN ('$NoLicStr', '$VoidLicStr')) ";
      } else if ($filter == "all")
      {  /* Not needed, but here to show that there is a filter all */
        $filterQuery = "";
      }
    }
    $params = array($left, $right, $type, $Agent_pk);

    $filterParms = $params;
    $searchFilter = $this->addSearchFilter($filterParms);
    $unorderedQuery = "FROM $tableName AS CP " .
        "INNER JOIN $uploadTreeTableName AS UT ON CP.pfile_fk = UT.pfile_fk " .
        $join .
        "WHERE " .
        " ( UT.lft  BETWEEN  $1 AND  $2 ) " .
        "AND CP.type = $3 " .
        " AND CP.agent_fk= $4 " .
        $sql_upload;
    $totalFilter = $filterQuery . " " . $searchFilter;

    $grouping = " GROUP BY content, hash ";

    $countQuery = "select count(*) from (SELECT substring(CP.content FROM 1 for 150) AS content, hash, count(*) $unorderedQuery  $totalFilter $grouping ) as K";

    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow($countQuery,
        $filterParms, __METHOD__.$tableName . ".count");
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $countAllQuery = "select count(*) from (SELECT substring(CP.content FROM 1 for 150) AS content, hash, count(*) $unorderedQuery$grouping ) as K";

    $iTotalRecordsRow = $this->dbManager->getSingleRow($countAllQuery, $params, __METHOD__,$tableName . "count.all");
    $iTotalRecords = $iTotalRecordsRow['count'];

    $range = "";

    $filterParms[] = $offset;
    $range .= ' OFFSET $' . count($filterParms);
    $filterParms[] = $limit;
    $range .= ' LIMIT $' . count($filterParms);

    $sql = "SELECT substring(CP.content FROM 1 for 150) AS content, hash,  count(*)  as copyright_count  " .
        $unorderedQuery . $totalFilter . $grouping . $orderString . $range;

    $statement = __METHOD__ . $filter.$tableName . $uploadTreeTableName;


    $this->dbManager->prepare($statement, $sql);

    $result = $this->dbManager->execute($statement, $filterParms);
    $rows = pg_fetch_all($result);
    pg_free_result($result);

    return array($rows, $iTotalDisplayRecords, $iTotalRecords);
  }

  /**
   * @return string
   */
  protected function doGetData()
  {
    $upload = GetParm("upload", PARM_INTEGER);
    /* check upload permissions */
    $UploadPerm = GetUploadPerm($upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }
    $this->uploadtree_tablename = GetUploadtreeTableName($upload);

    $item = GetParm("item", PARM_INTEGER);
    $agent_pk = GetParm("agent", PARM_STRING);
    $type = GetParm("type", PARM_STRING);
    $filter = GetParm("filter", PARM_STRING);
    $listPage = "copyright-list";

    header('Content-type: text/json');
    list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->GetTableData($upload, $item, $agent_pk, $type,$listPage, $filter);
    return (json_encode(array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => $aaData,
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords
        )
    )
    );
  }

  /**
   * @return string
   * @throws Exception
   */
  protected function doUpdate()
  {
    $id = GetParm("id", PARM_STRING);
    if (isset($id))
    {
      list($upload, $item, $hash,$type) = explode(",", $id);
      $this->uploadtree_tablename = GetUploadtreeTableName($upload);
      list($left, $right) = $this->uploadDao->getLeftAndRight($item, $this->uploadtree_tablename);

      list($tableName, $filter)=$this->getTableNameAndFilter($type, "");

      $sql_upload = "";
      if ('uploadtree_a' == $this->uploadtree_tablename)
      {
        $sql_upload = " AND UT.upload_fk=$upload ";
      }

      $content = GetParm("value", PARM_STRING);

      if (!$content)
      {
        header('Content-type: text/plain');
        return 'empty content not allowed';
      }

      global $SysConf;
      $userId = $SysConf['auth']['UserId'];

      $statementName = __METHOD__.$tableName;
      $combinedQuerry = "UPDATE $tableName AS CPR
                                SET  content = $4 , hash = md5 ($4)
                              FROM  $tableName as CP
                              INNER JOIN  $this->uploadtree_tablename AS UT ON CP.pfile_fk = UT.pfile_fk
                              WHERE CPR.ct_pk = CP.ct_pk
                                AND CP.hash =$1
                                AND   ( UT.lft  BETWEEN  $2 AND  $3 ) $sql_upload
                              RETURNING CP.* ";

      $this->dbManager->prepare($statementName, $combinedQuerry);
      $oldData = $this->dbManager->execute($statementName, array($hash, $left, $right, $content));

      if($type== "copyright") //no audit for other agents ?!
      {
        $insertQuerry = "INSERT into copyright_audit  (ct_fk,oldtext,user_fk,upload_fk, uploadtree_pk, pfile_fk  ) values ($1,$2,$3,$4,$5,$6)";
        while ($row = pg_fetch_assoc($oldData))
        {
          $this->dbManager->getSingleRow($insertQuerry, array($row['ct_pk'], $row['content'], $userId, $upload, $item, $row['pfile_fk']), __METHOD__ . "writeHist");
        }
      }
      $this->dbManager->freeResult($oldData);
      header('Content-type: text/plain');
      return 'success';
    }
    else
    {
      $text = _("Wrong request");
      echo "<h2>$text<h2>";
      return;
    }
  }

  protected function doDelete()
  {
    $id = GetParm("id", PARM_STRING);
    if (isset($id))
    {
      list($upload, $item, $hash, $type) = explode(",", $id);
      list($tableName, $filter)=$this->getTableNameAndFilter($type, "");

      $this->uploadtree_tablename = GetUploadtreeTableName($upload);
      list($left, $right) = $this->uploadDao->getLeftAndRight($item, $this->uploadtree_tablename);

      $sql_upload = "";
      if ('uploadtree_a' == $this->uploadtree_tablename)
      {
        $sql_upload = " AND UT.upload_fk=$upload ";
      }
      $deleteQuerry = " DELETE
                                from $tableName as CPR
                                USING $this->uploadtree_tablename  AS UT
                                where CPR.pfile_fk = UT.pfile_fk
                                and CPR.hash =$1
                                and ( UT.lft  BETWEEN  $2 AND  $3 ) $sql_upload";
      $this->dbManager->getSingleRow($deleteQuerry, array($hash, $left, $right), __METHOD__."deleteCopyright".$tableName);

      header('Content-type: text/json');
      return 'Successfully deleted';
    }
    else
    {
      $text = _("Wrong request");
      echo "<h2>$text<h2>";
      return;
    }
  }

  private function getTableNameAndFilter($type, $filter)
  {
    switch ($type) {
      case "ip" :
        $tableName = "ip";
        $filter="none";
        break;
      case "ecc" :
        $tableName = "ecc";
        $filter="none";
        break;
      default:
        $tableName = "copyright";
    }
    return array($tableName, $filter);
  }


}

;

$NewPlugin = new CopyrightHistogramProcessPost;
$NewPlugin->Initialize();