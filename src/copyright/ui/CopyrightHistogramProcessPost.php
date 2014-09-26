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
class CopyrightHistogramProcessPost  extends FO_Plugin {
    /**
   * @var string
   */
  private $uploadtree_tablename;

  /** @var array */
  private $filterParams;
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
    $this->Name = "copyrightHistogram-processPost";
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
    $this->filterParams=array();
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {

    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    $upload = GetParm("upload",PARM_INTEGER);
    /* check upload permissions */
    $UploadPerm = GetUploadPerm($upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }



    $item = GetParm("item",PARM_INTEGER);
    $agent_pk = GetParm("agent",PARM_STRING);
    $type = GetParm("type",PARM_STRING);
    $filter = GetParm("filter",PARM_STRING);






    $this->uploadtree_tablename = GetUploadtreeTableName($upload);


    header('Content-type: text/json');
    list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->GetTableData($upload, $item, $agent_pk, $type, $filter);
    return (json_encode(array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' =>$aaData,
            'iTotalRecords' =>$iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords
        )
    )
    );

  }

  /**
   * @param $row
   * @param $Uploadtree_pk
   * @param $Agent_pk
   * @param bool $normalizeString
   * @param string $filter
   * @param $type
   * @return array
   */
  private function fillTableRow( $row,  $Uploadtree_pk, $Agent_pk, $normalizeString=false ,$filter="", $type )
  {
//    $uniqueCount++;  I need to get this from extra queries
//    $totalCount += $row['copyright_count'];
    $output = array();

    $link = "<a href='";
    $link .= Traceback_uri();
    $URLargs = "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $row['hash'] . "&type=" . $type;
    if (!empty($filter)) $URLargs .= "&filter=$filter";
    $link .= $URLargs . "'>".$row['copyright_count']."</a>";
    $output[]=$link;


    if($normalizeString) {
      /* strip out characters we don't want to see
       This is a hack until the agent stops writing these chars to the db.
      */
      $S = $row['content'];
      $S = htmlentities($S);
      $S = str_replace("&Acirc;", "", $S); // comes from utf-8 copyright symbol
      $output []= $S;
    }
    else  {

      $output []= htmlentities($row['content']);
    }
    return $output;
  }


  private function GetTableData($upload, $item, $agent_pk, $type, $filter)
  {
    list ($rows, $iTotalDisplayRecords,$iTotalRecords ) = $this->getCopyrights($upload,$item,  $this->uploadtree_tablename ,$agent_pk, 0,$type,$filter);
    $aaData=array();
    if(!empty($rows))
    {
      foreach ($rows as $row)
      {
        $aaData [] = $this->fillTableRow($row,  $item, $agent_pk, false, $filter, $type);
      }
    }

    return array($aaData, $iTotalRecords, $iTotalDisplayRecords);

  }


    private function getOrderString(){

    $columnNamesInDatabase=array('copyright_count', 'content');

    $defaultOrder = CopyrightHistogram::returnSortOrder();

    $orderString = $this->dataTablesUtility->getSortingString($_GET,$columnNamesInDatabase, $defaultOrder);

    return $orderString;
  }

  private function getSearchString()
  {
    $searchPattern = GetParm('sSearch', PARM_STRING);
    if (empty($searchPattern))
    {
      return '';
    }
    $this->filterParams[] = "%$searchPattern%";
    return ' AND upload_filename ilike $'.count($this->filterParams).' ';
  }



  public function getCopyrights( $upload_pk, $Uploadtree_pk, $uploadTreeTableName , $Agent_pk, $hash = 0, $type, $filter)
  {
    $offset = GetParm('iDisplayStart',PARM_INTEGER);
    $limit = GetParm('iDisplayLength',PARM_INTEGER);


    $orderString = $this->getOrderString();
    $this->filterParams = array();
    $searchFilter = $this->getSearchString();

    list($left, $right) = $this->uploadDao->getLeftAndRight($Uploadtree_pk, $uploadTreeTableName);

    //! Set the default to none
    if($filter=="")  $filter = "none";

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND UT.upload_fk=$upload_pk ";
    }

    $join = "";
    $filterQuery ="";
    if( $filter == "legal" ) {
      $Copyright = "Copyright";
      $filterQuery  = " AND CP.content ILIKE ('$Copyright%') ";
    }
    else if ($filter == "nolics"){

      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $join  = " INNER JOIN license_file AS LF on  CP.pfile_fk =LF.pfile_fk ";
      $filterQuery =" AND LF.rf_fk IN (select rf_pk from license_ref where rf_shortname IN ('$NoLicStr', '$VoidLicStr')) ";
    }
    else if ($filter == "all") {  /* Not needed, but here to show that there is a filter all */
      $filterQuery ="";
    }

    $params = array($left,$right,$type,$Agent_pk);

    $filterParms = $params;
    foreach($this->filterParams as $par)
    {
      $filterParms[]=$par;
    }
    $unorderedQuery= "FROM copyright AS CP " .
    "INNER JOIN $uploadTreeTableName AS UT ON CP.pfile_fk = UT.pfile_fk " .
    $join.
    "WHERE " .
    " ( UT.lft  BETWEEN  $1 AND  $2 ) " .
    "AND CP.type = $3 ".
    " AND CP.agent_fk= $4 ".
    $sql_upload;
    $totalFilter = $filterQuery. " ". $searchFilter;

    $grouping = " GROUP BY content, hash ";

    $countQuery = "select count(*) from (SELECT substring(CP.content FROM 1 for 150) AS content, hash, count(*) $unorderedQuery  $totalFilter $grouping ) as K";

    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow($countQuery,
        $filterParms, __METHOD__ . ".count");
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $countAllQuery = "select count(*) from (SELECT substring(CP.content FROM 1 for 150) AS content, hash, count(*) $unorderedQuery$grouping ) as K";

    $iTotalRecordsRow = $this->dbManager->getSingleRow($countAllQuery, $params, __METHOD__ . "count.all");
    $iTotalRecords = $iTotalRecordsRow['count'];

    $range= "";

    $filterParms[] = $offset;
    $range .= ' OFFSET $'.count($filterParms);
    $filterParms[] = $limit;
    $range .= ' LIMIT $'.count($filterParms);

    $sql = "SELECT substring(CP.content FROM 1 for 150) AS content, hash,  count(*)  as copyright_count  " .
        $unorderedQuery .$totalFilter . $grouping  . $orderString. $range;

    $statement = __METHOD__ . $filter.$uploadTreeTableName;


    $this->dbManager->prepare($statement,$sql);

    $result = $this->dbManager->execute($statement,$filterParms);
    $rows = pg_fetch_all($result);
    pg_free_result($result);

    return array($rows, $iTotalDisplayRecords,$iTotalRecords );
  }


};

$NewPlugin = new CopyrightHistogramProcessPost;
$NewPlugin->Initialize();