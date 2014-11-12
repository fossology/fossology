<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014 Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * @history \file common-copyright-file.php
 * \brief This file contains common functions for getting copyright information
 */



use Fossology\Lib\Util\Object;
use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Db\DbManager;




class CopyrightLister extends Object
{
  private $includeContainer = FALSE;
  private $excluding_copyright = -1;
  private $including_copyright = -1;
  
  function __construct(){
    
  }
  
  public function setContainerInclusion($includeContainer)
  {
    $this->includeContainer = $includeContainer;
  }
  
  public function setExcludingCopyright($excludingCopyright)
  {
    $this->excluding_copyright = $excludingCopyright;
  }
  
  public function setIncludingCopyright($includingCopyright)
  {
    $this->including_copyright = $includingCopyright;
  }

  /**
   * \brief get copyright list of one specified uploadtree_id
   *
   * \pamam $uploadtree_pk - uploadtree id
   * \pamam $upload_pk - upload id
   * \param $type copyright type(all/statement/url/email)
   */
  function GetCopyrightList($uploadtree_pk, $upload_pk, $type)
  {
    global $container;
    /** @var DbManager $dbManager */
    $dbManager = $container->get('db.manager');
    if (empty($uploadtree_pk)) {
      $uploadtreeRec = $dbManager->getSingleRow('SELECT * FROM uploadtree WHERE parent IS NULL AND upload_fk=$1',
              array($upload_pk),
              $sqlLog=__METHOD__.'.getItem');
      $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
    }

    /* get last copyright agent_pk that has data for this upload */
    /** @var AgentsDao $agentDao */
    $agentDao = $container->get('dao.agents');
    $AgentRec = $agentDao->agentARSList($tableName="copyright_ars", $upload_pk, $limit=1);

    if ($AgentRec === false)
    {
      echo _("No data available \n");
      return;
    }
    $agent_pk = $AgentRec[0]["agent_fk"];
    $this->GetCopyrightList2($uploadtree_pk, $type, $agent_pk);
  }

  function GetCopyrightList2($uploadtree_pk, $type, $agent_pk)
  {
    global $PG_CONN;  

    /* get the top of tree */
    $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $toprow = pg_fetch_assoc($result);
    pg_free_result($result); 

    $uploadtree_tablename = GetUploadtreeTableName($toprow['upload_fk']);

    /* loop through all the records in this tree */
    $sql = "select uploadtree_pk, ufile_name, lft, rgt from $uploadtree_tablename 
                where upload_fk='$toprow[upload_fk]' 
                      and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
                      and ((ufile_mode & (1<<28)) = 0)";
    $container_sql = " and ((ufile_mode & (1<<29)) = 0)";
    /* include container or not */
    if (empty($this->includeContainer)) {
      $sql .= $container_sql; // do not include container
    }
    $sql .= " order by uploadtree_pk";
    $outerresult = pg_query($PG_CONN, $sql);
    DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

    /* Select each uploadtree row in this tree, write out text:
     * filepath : copyright list
     * e.g. copyright (c) 2011 hewlett-packard development company, l.p.
     */
    while ($row = pg_fetch_assoc($outerresult))
    { 
      $filepatharray = Dir2Path($row['uploadtree_pk'], $uploadtree_tablename);
      $filepath = "";
      foreach($filepatharray as $uploadtreeRow)
      {
        if (!empty($filepath)) $filepath .= "/";
        $filepath .= $uploadtreeRow['ufile_name'];
      }

      $copyright = $this->GetFileCopyright_string($agent_pk, 0, $row['uploadtree_pk'], $type) ;
      /** include and exclude together */
      if (-1 != $this->including_copyright && -1 != $this->excluding_copyright && !empty($this->including_copyright) && 
          !empty($this->excluding_copyright))
      {
        if (!empty($copyright) && stristr($copyright, $this->including_copyright) && 
            !stristr($copyright, $this->excluding_copyright)) ;
        else {
          continue;
        }
      }
      else if (
          /** no value set for -x and -X, show all files */ 
          (-1 == $this->including_copyright && -1 == $this->excluding_copyright) ||
          /** both value from -x and -X are empty, unmeaningful, show all files */
          (empty($this->including_copyright) && empty($this->excluding_copyright)) ||
          /** just show files without copyright no matter if excluding_copyright */
          (empty($this->including_copyright) && empty($copyright)) ||
          /** just show files with copyright */
          (empty($this->excluding_copyright) && !empty($copyright)) ||
          /** include  */
          (-1 != $this->including_copyright && !empty($this->including_copyright) && !empty($copyright) && stristr($copyright, $this->including_copyright))  ||
          /** exclude */
          (-1 != $this->excluding_copyright && !empty($this->excluding_copyright) && !empty($copyright) && !stristr($copyright, $this->excluding_copyright))) ;
      else continue;
      {
        $V = $filepath . ": ". $copyright;
        print "$V";
        print "\n";
      }
    } 
    pg_free_result($outerresult);
  }


/**
 * \brief get all the copyright for a single file or uploadtree
 * 
 * \param $agent_pk - agent id
 * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
 * \param $uploadtree_pk - (used only if $pfile_pk is empty)
 * \param $type - copyright statement/url/email
 * 
 * \return Array of file copyright CopyrightArray[ct_pk] = copyright.content
 * FATAL if neither pfile_pk or uploadtree_pk were passed in
 */
function GetFileCopyrights($agent_pk, $pfile_pk, $uploadtree_pk, $type)
{
  global $PG_CONN;

  if (empty($agent_pk)) Fatal("Missing parameter: agent_pk", __FILE__, __LINE__);

  // if $pfile_pk, then return the copyright for that one file
  if ($pfile_pk)
  {
    $sql = "SELECT ct_pk, content 
              from copyright
              where pfile_fk='$pfile_pk' and agent_fk=$agent_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else if ($uploadtree_pk)
  {
    // Find lft and rgt bounds for this $uploadtree_pk 
    $sql = "SELECT lft, rgt, upload_fk FROM uploadtree
                   WHERE uploadtree_pk = $uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $typesql = '';
    if ($type && "all" != $type) $typesql = "and type = '$type'";

    //  Get the copyright under this $uploadtree_pk
    $sql = "SELECT ct_pk, content from copyright ,
                  (SELECT distinct(pfile_fk) as PF from uploadtree
                     where upload_fk=$upload_pk
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$agent_pk $typesql;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  else Fatal("Missing function inputs", __FILE__, __LINE__);

  $CopyrightArray = array();
  while ($row = pg_fetch_assoc($result))
  {
    $CopyrightArray[$row['ct_pk']] = $row["content"];
  }
  pg_free_result($result);
  return $CopyrightArray;
}


  /**
   * \brief  returns copyright list as a single string
   * \param $agent_pk - agent id
   * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
   * \param $uploadtree_pk - (used only if $pfile_pk is empty)
   * \param $type - copyright statement/url/email
   *
   * \return copyright string for specified file
   */
  function GetFileCopyright_string($agent_pk, $pfile_pk, $uploadtree_pk, $type)
  {
    $CopyrightStr = "";
    $CopyrightArray = $this->GetFileCopyrights($agent_pk, $pfile_pk, $uploadtree_pk, $type);
    $first = true;
    foreach($CopyrightArray as $ct)
    {
      if ($first)
      $first = false;
      else
      $CopyrightStr .= " ,";
      $CopyrightStr .= $ct;
    }

    return $CopyrightStr;
  }

}

