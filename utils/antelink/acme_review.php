<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

define("TITLE_acme_review", _("ACME Review"));

/**
 * \brief Sort project by count desc
 * \todo  Since this is just a prototype, there is no acme_ars table
 *        This means that you can get incomplete data for an upload that has pfiles
 *        shared with another upload.  You MUST run fo_antelink.php BEFORE
 *        running this plugin on an upload!  If this happens you need to do a 
 *          delete from acme_upload where upload_fk=NNNN;
 *        Then run fo_antelink.php followed by this plugin.
 */
function proj_cmp($rowa, $rowb)
{
  $key1 = $rowb['count'] - $rowa['count'];
  if ($key1) return $key1;

  // secondary key - project_name ascending
  return (strnatcasecmp($rowa['project_name'], $rowb['project_name']));
}

class acme_review extends FO_Plugin
{
  var $Name       = "acme_review";
  var $Title      = TITLE_acme_review;
  var $Version    = "1.0";
  var $MenuList   = "";
  var $MenuOrder  = 110;
  var $Dependency = array("browse", "view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoHTML  = 1;  // prevent the http header from being written in case we have to download a file

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
  	global $PG_CONN;
    if (GetParm("mod", PARM_STRING) == $this->Name) 
    {
      $detail = GetParm("detail",PARM_INTEGER);
      $upload_pk = GetParm("upload",PARM_INTEGER);
      $pfile_fk = GetParm("item",PARM_INTEGER);
	  $parent = GetParm("parent",PARM_INTEGER);
      if((!empty($upload_pk)) && (!empty($pfile_fk)))
	    {
	    	$sql = "select contained_subprojects from acme_project_hierarchy where upload_fk='$upload_pk' and pfile_fk='$pfile_fk' and parent is not null";
		    $uploadtreepkSet_result = pg_query($PG_CONN, $sql);
		    DBCheckResult($uploadtreepkSet_result, $sql, __FILE__, __LINE__);
		    $Row = pg_fetch_assoc($uploadtreepkSet_result);
		    $uploadtreepkSet = $Row['contained_subprojects'];
		    pg_free_result($uploadtreepkSet_result);
	    }
      if ($detail)
      {
        $text = _("ACME High Level Review");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=0&item=$pfile_fk";
      }
      else
      {
        $text = _("ACME Low Level Review");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=1";
      }

      // micro menu when in acme_review
      menu_insert("acme::$text", 1, $URI, $text);
    }
    else 
    {
		$upload_pk = GetParm("upload",PARM_INTEGER);
		if(!empty($upload_pk))
		{
			//if package has not fetched data from ante
			$acme_logfile = "log_acme_".$upload_pk;
			$fetched = exec('tail -1 /tmp/'.$acme_logfile, $outputArr= array(), $return_var);
		}
		if($return_var == 0)
		{
			//find log file
			//check if fetching is over
			if (strpos($fetched,'files tagged out of') !== false)
			{
				$text2 = _("ACME Review");
				$URI = $this->Name . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
			}
			else
			{
				//fetching is not over
				$text2 = _("ACME Fetcher");
				$URI = "acme_fetch" . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
			}
		}
		else
		{
			//no log file found
			$text2 = _("ACME Fetcher");
			$URI = "acme_fetch" . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
		}
      // micro menu item when not in acme_review
      menu_insert("Browse::$text2", 1, $URI, $text2);
    }
  } // RegisterMenus()

  /**
   * \brief Find all components in the uploade tree, excluding nested artificat dir and pfile_fk=0
   *        Once you get an acme hit, don't look further down the hierarch.
   * \param $UploadPk the key in upload table
   */
  function CountUploadtreeComponent($UploadPk)
  {
    global $PG_CONN;

    /* See if there is already an acme project for this pfile */
	$sql = "select (rgt - lft + 1)/2 - noncountinnumber as count from uploadtree, 
					(select count(uploadtree_pk) as noncountinnumber from uploadtree where uploadtree.upload_fk=$UploadPk and (pfile_fk=0 or ((ufile_mode & (1<<28))!=0))) as non
					 where uploadtree.upload_fk=$UploadPk and parent is null";
    $result = pg_query($PG_CONN, $sql);
    $resultComponents = 0;
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) 
    {
    	$row = pg_fetch_assoc($result);
    	$resultComponents = $row['count'];
    }
    pg_free_result($result);
    return $resultComponents;
  }

  /**
   * \brief Find all the acme projects in a hierarchy, starting with $uploadtree_pk.
   *        Once you get an acme hit, still look further down the hierarch for different acme project;
   *        don't look for current node's found projects.
   * \param $uploadtreeRow Array containing uploadtree.uploadtree_pk, pfile_fk, lft, rgt
   * \param $acme_project_array, key is acme_project_pk, value is the row array
   * \param $excludedProject, memo the projects which are not counted in
   * \param $Scanned_files_array, memo scanned files
   */
  function FindACMEProjects($uploadtreeRow, &$acme_project_array, $excludedProject, &$Scanned_files_array)
  {
    global $PG_CONN;

    /* See if there is already an acme project for this pfile */
    $sql = "select acme_project_fk from acme_pfile where pfile_fk='$uploadtreeRow[pfile_fk]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) 
    {
      /* found a project */
      $acme_pfileRow = pg_fetch_assoc($result);

      // retrieve the acme_project record to go with the found acme_project_fk
      $sql = "select acme_project.* from acme_project,acme_pfile where acme_pfile.acme_project_fk=acme_project_pk and pfile_fk='$uploadtreeRow[pfile_fk]' and acme_project_pk not in($excludedProject)";
      $projresult = pg_query($PG_CONN, $sql);
      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
      $newExcludedProject = $excludedProject;
      if(pg_num_rows($projresult) > 0) 
      {
      	//remark the files which are identified because itself is hit or its parent is hit
      	  $sql = "select uploadtree_pk from uploadtree where uploadtree.upload_fk=$uploadtreeRow[upload_fk] and lft>=$uploadtreeRow[lft] and rgt<=$uploadtreeRow[rgt] and pfile_fk!=0 and ((ufile_mode & (1<<28))=0)";
	      $childrenResult = pg_query($PG_CONN, $sql);
	      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
	      while ($componentRow = pg_fetch_assoc($childrenResult))
	      {
	      	$Scanned_files_array[] = $componentRow['uploadtree_pk'];
	      }
      	pg_free_result($childrenResult);
      	while ($projectRow = pg_fetch_assoc($projresult))
      	{
      		$newExcludedProject = $newExcludedProject.','.$projectRow['acme_project_pk'];
      		if (empty($acme_project_array[$projectRow['acme_project_pk']]))
      		{
      			$acme_project_array[$projectRow['acme_project_pk']] = $projectRow;
      		}
      		$acme_project_array[$projectRow['acme_project_pk']]['count'] = $acme_project_array[$projectRow['acme_project_pk']]['count'] + (($uploadtreeRow['rgt'] - $uploadtreeRow['lft']) +1)/2;
	        /* count nested artificat dir and pfile_fk=0 */
			    $sql = "select count(uploadtree_pk) as noncountinnumber from uploadtree where uploadtree.upload_fk=$uploadtreeRow[upload_fk]
									and lft>$uploadtreeRow[lft] and rgt<$uploadtreeRow[rgt]
									and (pfile_fk=0 or ((ufile_mode & (1<<28))!=0))";
			    $countresult = pg_query($PG_CONN, $sql);
			    DBCheckResult($countresult, $sql, __FILE__, __LINE__);
			    if (pg_num_rows($countresult) > 0) 
			    {
			      $countresultRow = pg_fetch_assoc($countresult);
			      // list artifact containers
			      $acme_project_array[$projectRow['acme_project_pk']]['count'] = $acme_project_array[$projectRow['acme_project_pk']]['count'] - $countresultRow['noncountinnumber'];
			    }
	 				pg_free_result($countresult);
				}
	    }
	    pg_free_result($projresult);
	    if (($uploadtreeRow['rgt'] - $uploadtreeRow['lft']) > 1){
	      /* check each child */
	      $sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt from uploadtree where parent= $uploadtreeRow[uploadtree_pk]";
	      $childrenResult = pg_query($PG_CONN, $sql);
	      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
	      while ($child = pg_fetch_assoc($childrenResult))
	      {
	        $this->FindACMEProjects($child, $acme_project_array, $newExcludedProject, $Scanned_files_array);
	      }
	      pg_free_result($childrenResult);
	    }
      return;
    }
    else
    {
      /* check each child */
      $sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt from uploadtree where parent= $uploadtreeRow[uploadtree_pk]";
      $childrenResult = pg_query($PG_CONN, $sql);
      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
      while ($child = pg_fetch_assoc($childrenResult))
      {
		$this->FindACMEProjects($child, $acme_project_array, $excludedProject, $Scanned_files_array);
      }
      pg_free_result($childrenResult);
    }
    pg_free_result($result);

    return;
  } // FindACMEProjects()

  /**
   * \brief Given upload tree information , return all direct sub projects under the current position.
   * \param $upload_pk
   * \param $uploadtreepkSet
   * \return array of acme_project records, including count
   */
  function GetSubProjectArray($upload_pk, $uploadtreepkSet)
  {
    global $PG_CONN;
    $acme_project_array = array();  // acme project array to return
    $acme_project_tmp_array = array();  // acme project array used internally
    if (empty($uploadtreepkSet))
		{
			$sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent from uploadtree where upload_fk='$upload_pk' and parent is null";
		}
		else
		{
			$sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent from uploadtree where upload_fk='$upload_pk' and parent in($uploadtreepkSet)";
		}
		$result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($child = pg_fetch_assoc($result))
    {
    	if(empty($child['parent']))
    	{
    		$parent = $child['uploadtree_pk'];
    	}
    	else
    	{
    		$parent = $child['parent'];
    	}
      $this->FindSubACMEProjects($child, $acme_project_tmp_array, $parent);
    }
    pg_free_result($result);
    //add flags to display projects' name as links or labels. links: the project contains sub projects; labels: the project does not contain sub projects
    //The segment could be removed if we do not need labels in the page even those projects do not contain sub projects.
    foreach($acme_project_tmp_array as $acme_project_tmp)
    {
    	$acme_project = $acme_project_tmp;
    	$acme_project['contains_subproject_flag'] = (int)$this->ValidateSubACMEProjects($upload_pk, $acme_project_tmp['contained_subprojects']);
    	$acme_project_array[] = $acme_project;
    }
    return $acme_project_array;
  }
  /**
   * \brief Given upload tree information , return if there are sub projects under the current position.
   * \param $upload_pk
   * \param $uploadtreepkSet
   * \return true: sub projects are found, false: none project is found
   */
  function ValidateSubACMEProjects($upload_pk, $uploadtreepkSet)
  {
    global $PG_CONN;
    if (empty($uploadtreepkSet))
		{
			return false;
		}
		else
		{
			$sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent from uploadtree where upload_fk='$upload_pk' and parent in($uploadtreepkSet)";
		}
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($child = pg_fetch_assoc($result))
    {
      //the flag to mark if the current hierarch has sub projects under it
      $validFlag = $this->CheckSubACMEProjects($child);
      if ($validFlag === true)
      {
   	    pg_free_result($result);
      	return true;
      }
    }
    pg_free_result($result);
    return false;
  }
  /**
   * \brief Find if there are acme projects under the current hierarchy, starting with $uploadtree_pk.
   *        Once you get an acme hit, stop to look further down the hierarch for its acme project;
   *        If you dit not get acme hit, look further down the hierarch for possible acme project;
   * \param $uploadtreeRow Array containing uploadtree.uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent
   */
  function CheckSubACMEProjects($uploadtreeRow)
  {
    global $PG_CONN;
    /* See if there is already an acme project for this pfile */
    $sql = "select acme_project_fk from acme_pfile where pfile_fk='$uploadtreeRow[pfile_fk]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) 
    {
      pg_free_result($result);
      return true;
    }
    else
    {
      /* check each child */
      $sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent from uploadtree where parent= $uploadtreeRow[uploadtree_pk]";
      $childrenResult = pg_query($PG_CONN, $sql);
      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
      while ($child = pg_fetch_assoc($childrenResult))
      {
      	//the flag to mark if the current hierarch has sub projects under it
				$validFlag = $this->CheckSubACMEProjects($child);
				if ($validFlag === true)
	      {
	   	    pg_free_result($childrenResult);
      		return true;
	      }
      }
      pg_free_result($childrenResult);
    }
    pg_free_result($result);
    return false;
  }
  /**
   * \brief Find direct acme projects under the current hierarchy, starting with $uploadtree_pk.
   *        Once you get an acme hit, stop to look further down the hierarch for its acme project;
   *        If you dit not get acme hit, look further down the hierarch for possible acme project;
   * \param $uploadtreeRow Array containing uploadtree.uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent
   * \param $acme_project_array, key is acme_project_pk, value is the row array
   */
  function FindSubACMEProjects($uploadtreeRow, &$acme_project_array, $parent)
  {
    global $PG_CONN;

    /* See if there is already an acme project for this pfile */
    $sql = "select acme_project_fk from acme_pfile where pfile_fk='$uploadtreeRow[pfile_fk]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) 
    {
      /* found a project */
      $acme_pfileRow = pg_fetch_assoc($result);

      // retrieve the acme_project record to go with the found acme_project_fk
      $sql = "select acme_project.* from acme_project,acme_pfile where acme_pfile.acme_project_fk=acme_project_pk and pfile_fk='$uploadtreeRow[pfile_fk]'";
      $projresult = pg_query($PG_CONN, $sql);
      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
      if(pg_num_rows($projresult) > 0) 
      {
      	while ($projectRow = pg_fetch_assoc($projresult))
      	{
      		if (empty($acme_project_array[$projectRow['acme_project_pk']]))
      		{
      			$acme_project_array[$projectRow['acme_project_pk']] = $projectRow;
      			$acme_project_array[$projectRow['acme_project_pk']]['uploadtree_fk'] = $uploadtreeRow['uploadtree_pk'];
      			$acme_project_array[$projectRow['acme_project_pk']]['pfile_fk'] = $uploadtreeRow['pfile_fk'];
      			$acme_project_array[$projectRow['acme_project_pk']]['parent'] = $parent;
      		}
      		$acme_project_array[$projectRow['acme_project_pk']]['count'] = $acme_project_array[$projectRow['acme_project_pk']]['count'] + (($uploadtreeRow['rgt'] - $uploadtreeRow['lft']) +1)/2;
      		//set uploadtree pk for further sub search shown in projects link
      		if (empty($acme_project_array[$projectRow['acme_project_pk']]['contained_subprojects']))
      		{
	      		$acme_project_array[$projectRow['acme_project_pk']]['contained_subprojects'] = $uploadtreeRow['uploadtree_pk'];
	      	}
	      	else
	      	{
	      		$acme_project_array[$projectRow['acme_project_pk']]['contained_subprojects'] = $acme_project_array[$projectRow['acme_project_pk']]['contained_subprojects'].','.$uploadtreeRow['uploadtree_pk'];
	      	}
	      	
	        /* count nested artificat dir and pfile_fk=0 */
			$sql = "select count(uploadtree_pk) as noncountinnumber from uploadtree where uploadtree.upload_fk=$uploadtreeRow[upload_fk]
								and lft>$uploadtreeRow[lft] and rgt<$uploadtreeRow[rgt]
								and (pfile_fk=0 or ((ufile_mode & (1<<28))!=0))";
			$countresult = pg_query($PG_CONN, $sql);
			DBCheckResult($countresult, $sql, __FILE__, __LINE__);
			if (pg_num_rows($countresult) > 0) 
			{
			  $countresultRow = pg_fetch_assoc($countresult);
			  // list artifact containers
			  $acme_project_array[$projectRow['acme_project_pk']]['count'] = $acme_project_array[$projectRow['acme_project_pk']]['count'] - $countresultRow['noncountinnumber'];
			}
	 		pg_free_result($countresult);
      	}
      }
      pg_free_result($projresult);
      return;
    }
    // look further down the hierarch for possible acme project recursively. Stop once find one or traverse through the end of the hierarch
    else
    {
      /* check each child */
      $sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt, parent from uploadtree where parent= $uploadtreeRow[uploadtree_pk]";
      $childrenResult = pg_query($PG_CONN, $sql);
      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
      while ($child = pg_fetch_assoc($childrenResult))
      {
				$this->FindSubACMEProjects($child, $acme_project_array, $parent);
      }
      pg_free_result($childrenResult);
    }
    pg_free_result($result);

    return;
    
  }
  /**
   * \brief Given an upload , return all the unique projects found.
   * \param $upload_pk
   * \param $MinCount unused
   * \return array of acme_project records, including count
   */
  function GetProjectArray0($upload_pk, $nomosAgentpk, $MinCount=1)
  {
    global $PG_CONN;
    $acme_project_array = array();  // acme project array to return
    $Scanned_files_array = array();  // count identified file number

    $sql = "select uploadtree_pk, upload_fk, pfile_fk, lft, rgt from uploadtree where upload_fk='$upload_pk' and parent is null";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $this->FindACMEProjects($row, $acme_project_array, '0',$Scanned_files_array);
    pg_free_result($result);
    $Scanned_files_array = array_unique($Scanned_files_array);
    $CountPossibleAcmeComponent = count($Scanned_files_array);
		echo "Identified File Number: ".$CountPossibleAcmeComponent."<br/>";
    return $acme_project_array;
  }


  /**
   * \brief Given an upload , return all the unique projects found.
   * \param $upload_pk
   * \param $nomosAgentpk
   * \param $MinCount minimum file count to be included in returned array, default 1
   * \return array of acme_project records, including count
   */
  function GetProjectArray1($upload_pk, $nomosAgentpk, $MinCount=1)
  {
    global $PG_CONN;
    $acme_project_array = array();  // acme project array to return
    $idx = 0;

    $sql = "select distinct(acme_project_fk) as acme_project_fk, count(acme_project_fk) as filecount from acme_pfile
            right join uploadtree on uploadtree.pfile_fk=acme_pfile.pfile_fk where upload_fk=$upload_pk
            group by acme_project_fk order by filecount desc";
	  $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result))
    {
      if ($row['filecount'] < $MinCount) break;

      // retrieve the acme_project record to go with the found acme_project_fk
      $sql = "select * from acme_project where acme_project_pk='$row[acme_project_fk]'";
      $projresult = pg_query($PG_CONN, $sql);
      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
      if(pg_num_rows($projresult) > 0) 
      {
        $acme_project_array[$idx] = pg_fetch_assoc($projresult);
        $acme_project_array[$idx]['count'] = $row['filecount'];
        $idx++;
      }
    }

    return($acme_project_array);
  } // GetProjectArray1()


  /** 
   * \brief create the HTML to display the form showing the found projects
   * \param $acme_project_array
   * \param $upload_pke
   * \return HTML to display results
   */
  function HTMLForm($acme_project_array, $upload_pk, $acme_project_hierarchy)
  {
    $Outbuf = "";
    $uploadtreeRec = GetSingleRec("uploadtree", "where upload_fk=$upload_pk and parent is null");
    $Outbuf .= Dir2Browse($this->Name,$uploadtreeRec['uploadtree_pk'], NULL, 1, "acme");
    $OutHierarchy = $this->Dir2BrowseForAcmeHierarchy($this->Name,"acme_project_hierarchy", $acme_project_hierarchy);
    $Outbuf .= $OutHierarchy;
    $Outbuf .= "<p>";
 	$CountAcmeComponent = 0;

    $URI = "?mod=" . $this->Name . Traceback_parm_keep(array( "page", "upload", "folic", "detail"));
    $Outbuf .= "<form action='" . $URI . "' method='POST'>\n";

    $Outbuf .= "<table border=1>";
    $Outbuf .= "<tr>";
    //$text = _('Include');
    //$Outbuf .= "<th>$text</th>";
    $text = _('Project');
    $Outbuf .= "<th>$text</th>";
    $text = _('Files');
    $Outbuf .= "<th>$text</th>";
    $text = _('URL');
    $Outbuf .= "<th>$text</th>";
    $text = _('Description');
    $Outbuf .= "<th>$text</th>";
    $text = _('License');
    $Outbuf .= "<th>$text</th>";
    $text = _('Version');
    $Outbuf .= "<th>$text</th>";
    $Outbuf .= "</tr>";

    /* For alternating row background colors */
    $RowStyle1 = "style='background-color:lavender'";
    $RowStyle2 = "style='background-color:lightyellow'";
    $ColorSpanRows = 1;  // Alternate background color every $ColorSpanRows
    $RowNum = 0;
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
    $folic = GetParm("folic",PARM_INTEGER);

    foreach ($acme_project_array as $project)
    {
      /* Set alternating row background color - repeats every $ColorSpanRows rows */
      $RowStyle = (($RowNum++ % (2*$ColorSpanRows))<$ColorSpanRows) ? $RowStyle1 : $RowStyle2;

      $Outbuf .= "<tr $RowStyle>";
      $Checked = $project['include'] == 't' ? "checked=\"checked\"" : '' ;
      if($detail)
      {
      	// low level
   	    $Outbuf .= "<td>$project[project_name]</td>";
      }
      else
      {
      	// high level
      	if ($project['contains_subproject_flag'])
      	{
	        $ProjectURL = Traceback_uri() . "?mod=acme_review&upload=$upload_pk&detail=$detail&folic=$folic&item=$project[pfile_fk]&parent=$project[parent]";
		      $Outbuf .= "<td><a href='$ProjectURL'>$project[project_name]</a></td>";
		    }
		    else
		    {
		    	$Outbuf .= "<td>$project[project_name]</td>";
		    }
      }
      $ProjectListURL = Traceback_uri() . "?mod=acme_files&acme_project=$project[acme_project_pk]&upload=$upload_pk&project_name=$project[project_name]&detail=$detail&folic=$folic&item=$project[pfile_fk]&parent=$project[parent]";
      $Outbuf .= "<td><a href='$ProjectListURL'>$project[count]</a></td>";
      $CountAcmeComponent = $CountAcmeComponent + $project[count];
      $Outbuf .= "<td><a href='$project[url]'>$project[url]</a></td>";
      $Outbuf .= "<td>" . htmlentities($project['description']) . "</td>";
      $Outbuf .= "<td>$project[licenses]</td>";
      $Outbuf .= "<td>$project[version]</td>";
      $Outbuf .= "</tr>";
    }
    $Outbuf .= "</table>";

    $Outbuf .= "$RowNum rows found<br>";

    if ($detail)
    {
    	//low level review
    	$Outbuf .= "$CountAcmeComponent components' project information are identified based on third party repository<br>";
    }
    /*
    $text = _("Save and Generate SPDX file");
    $Outbuf .= "<p><input type='submit' value='$text' name='spdxbtn'>\n";
    $text = _("Save");
    $Outbuf .= "&nbsp;&nbsp;&nbsp;<input type='submit' value='$text' name='savebtn'>\n";
    */
    $Outbuf .= "</form>\n";
    /*******  END Input form  *******/
    
    return $Outbuf;
  }


  /** 
   * \brief Populate the acme_upload table for this upload
   * \param $acme_project_array
   * \param $upload_pk
   * \param $detail   0=high level view, 1=low level view
   * \return HTML to display results
   */
  function Populate_acme_upload($acme_project_array, $upload_pk, $detail)
  {
    global $PG_CONN;

    foreach ($acme_project_array as $project)
    {
      $sql = "insert into acme_upload (upload_fk, acme_project_fk, include, detail, count) values ($upload_pk, $project[acme_project_pk], true, $detail, $project[count])";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
  }


  /** 
   * \brief Write and return the SPDX file as a string
   * \param $acme_project_array
   * \return SPDX file as string
   */
  function GenerateSPDX($acme_project_array)
  {
    global $SysConf;

    $spdx = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $spdx .='<rdf:RDF' . "\n";
    $spdx .= '    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n";
    $spdx .= '    xmlns="http://spdx.org/rdf/terms#"' . "\n";
    $spdx .= '    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">' . "\n";
    $spdx .= '    <SpdxDocument rdf:about="http://www.spdx.org/tools#SPDXANALYSIS">' . "\n";
    $spdx .= '      <specVersion>SPDX-1.1</specVersion>' . "\n";
    $spdx .= '      <dataLicense rdf:about="http://spdx.org/licenses/PDDL-1.0" />' . "\n";
    $spdx .= '      <CreationInfo>' . "\n";
    $spdx .= " <creator>Tool: FOSSology v " . $SysConf['BUILD']['VERSION'] . " svn " . $SysConf['BUILD']['SVN_REV'] . "</creator>\n";
    $spdx .= "<created>" . date('c') . "</created>\n";   // date-time in ISO 8601 format
    $spdx .= '</CreationInfo>' . "\n";

	$in_encoding = iconv_get_encoding("input_encoding");
    foreach($acme_project_array as $project)
    {
//debugprint($project, "Project");
      $spdx .= "<Package>\n";
      $spdx .= '<name>' . str_replace("&", " and ", strip_tags($project['project_name'])) . '</name>' . "\n";
      $spdx .= "<versionInfo>$project[version]</versionInfo>\n";
      $spdx .= "<licenseDeclared>$project[licenses]</licenseDeclared>\n";
      $spdx .= "<sourceInfo>ProjectURL: $project[url]</sourceInfo>\n";
      $spdx .= '<description>' . str_replace("&", " and ", strip_tags($project['description'])) . '</description>' . "\n";
      $spdx .= "</Package>\n";
    }
/*
			<packageSupplier>Organization: FSF (info@fsf.com)</packageSupplier>
			<packageOriginator>Organization: FSF (info@fsf.com)</packageOriginator>
			<packageDdownloadLocation>http://ftp.gnu.org/gnu/coreutils/</packageDdownloadLocation>
			<packageFileName>coreutils-8.12.tar.gz</packageFileName>
			<sourceInfo>
				mechanism: git
				repository: git://git.sv.gnu.org/coreutils
				branch: master
				tag: v8.12
			</sourceInfo>
		</Package>
*/

    $spdx .= "  </SpdxDocument> </rdf:RDF>\n";
    return $spdx;
  }
	/** borrow from Dir2Browse@common-dir.php
	 * \brief Get an html linked string of a project's hierarchy path.
	 *
	 * \param $Mod - Module name (e.g. "browse")
	 * \param $tablename
	 * \param $acme_project_hierarchy
	 *
	 * \return string of browse paths
	 */
  function Dir2BrowseForAcmeHierarchy($Mod, $tablename="acme_project_hierarchy", $acme_project_hierarchy)
  {
  	$Uri = Traceback_uri() . "?mod=$Mod";

	  /* Get array of upload recs for this path, in top down order.
	   */
	  $LinkLast = NULL;
	  $Path = $acme_project_hierarchy;
	  $Last = &$Path[count($Path)-1];
	  $V .= "<font class='text'>\n";
	  $FirstPath=1; /* every firstpath belongs on a new line */
	  /* Show the path within the upload */
	  if ($FirstPath!=0)
	  {
	    for($p=0; !empty($Path[$p]['acme_project_hierarchy_pk']); $p++)
	    {
	      $P = &$Path[$p];
	
	      if (!$FirstPath) { $V .= "/ "; }
	      if (!empty($LinkLast) || ($P != $Last))
	      {
	        if ($P == $Last)
	        {
	          $Uri = Traceback_uri() . "?mod=$LinkLast";
	        }
	        $V .= "<a href='$Uri&upload=" . $P['upload_fk'] . "&detail=0&item=". $P['pfile_fk'] . "&parent=". $P['parent'] . "'>";
	      }
	
	      $V .= $P['project_name'];

	      if (!empty($LinkLast) || ($P != $Last))
	      {
	        $V .= "</a>";
	      }
	      $FirstPath = 0;
	    }
	  }
	  $V .= "</font>\n";
    return($V);
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    global $Plugins;
    global $PG_CONN;

//phpinfo();
    $CriteriaCount = 0;
    $V="";
    $GETvars="";
    $upload_pk = GetParm("upload",PARM_INTEGER);
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
    $folic = GetParm("folic",PARM_INTEGER);
    $savebtn = GetParm("savebtn",PARM_RAW);
    $spdxbtn = GetParm("spdxbtn",PARM_RAW);
    $pfile_fk = GetParm("item",PARM_INTEGER);
    $parent = GetParm("parent",PARM_INTEGER);
    //$uploadtreepkSet = GetParm("itemset",PARM_STRING);
    if(!empty($pfile_fk))
    {
    	if(empty($parent))
    	{
    		$sql = "select contained_subprojects from acme_project_hierarchy where upload_fk='$upload_pk' and pfile_fk='$pfile_fk' and parent is null";
    	}
    	else
    	{
	    	$sql = "select contained_subprojects from acme_project_hierarchy where upload_fk='$upload_pk' and pfile_fk='$pfile_fk' and ((parent is null) or parent=$parent)";
	    }
	    $uploadtreepkSet_result = pg_query($PG_CONN, $sql);
	    DBCheckResult($uploadtreepkSet_result, $sql, __FILE__, __LINE__);
	    $Row = pg_fetch_assoc($uploadtreepkSet_result);
	    $uploadtreepkSet = $Row['contained_subprojects'];
	    pg_free_result($uploadtreepkSet_result);
    }

    $acme_project_array = array();

    $agent_pk = LatestAgentpk($upload_pk, "nomos_ars");
    if (empty($agent_pk))
    {
      echo "Missing fossology license data.  Run a license scan on this upload.<br>";
      exit;
    }
    $uploadtree_tablename = GetUploadtreeTableName($upload_pk);

    // Check if we have data in the acme_upload table, if not then load it
    $acme_uploadRec = GetSingleRec("acme_upload", "where upload_fk=$upload_pk ");
    if (empty($acme_uploadRec))
    {
      // populate acme_upload
      $MinCount = 1;
      $nomosAgentpk = LatestAgentpk($upload_pk, "nomos_ars");
      $acme_project_array = $this->GetProjectArray1($upload_pk, $nomosAgentpk, $MinCount);  // low level
      $this->Populate_acme_upload($acme_project_array, $upload_pk, 1);
      $acme_project_array = $this->GetSubProjectArray($upload_pk, $uploadtreepkSet); // high level
      $this->Populate_acme_upload($acme_project_array, $upload_pk, 0);
    }
		
		if ($detail)
		{
			//get all projects information for low level revie
			    $sql = "select * from acme_upload, acme_project where acme_project_pk=acme_project_fk and detail=$detail and upload_fk=$upload_pk";
			    $result = pg_query($PG_CONN, $sql);
			    DBCheckResult($result, $sql, __FILE__, __LINE__);
			    $acme_project_array = pg_fetch_all($result);
		}
		else
		{
			//get root package name
			$sql = "select ufile_name from uploadtree where upload_fk='$upload_pk' and parent is null";
			$ufile_name_result = pg_query($PG_CONN, $sql);
			DBCheckResult($ufile_name_result, $sql, __FILE__, __LINE__);
			$Row = pg_fetch_assoc($ufile_name_result);
			$ufile_name = $Row['ufile_name'];
			pg_free_result($ufile_name_result);
	    
			//get sub projects information for high level review
			if (empty($uploadtreepkSet))
			{
				 $sql = "select * from acme_project_hierarchy where upload_fk='$upload_pk'";
			}
			else
			{
				$sql = "select * from acme_project_hierarchy where upload_fk='$upload_pk' and parent in($uploadtreepkSet) and contained_subprojects <> parent::varchar(255)";
			}
			$result = pg_query($PG_CONN, $sql);
			DBCheckResult($result, $sql, __FILE__, __LINE__);
			if (pg_num_rows($result) <= 0) 
			{
				//if hierarchy records are not found, update hierarchy table based on acme_project    	
				if(empty($acme_project_array))
				{
					$acme_project_array = $this->GetSubProjectArray($upload_pk, $uploadtreepkSet);
				}
				//update acme_project_hierarchy table
				foreach ($acme_project_array as $project)
				{
						if($project['parent']==$project['contained_subprojects'])
						{
							$sql = "insert into acme_project_hierarchy (upload_fk, uploadtree_fk, pfile_fk, acme_project_fk, parent, contains_subproject_flag, contained_subprojects, count) values ($upload_pk, nullif('$project[parent]','')::integer, $project[pfile_fk], $project[acme_project_pk], nullif('$project[parent]','')::integer, $project[contains_subproject_flag], '$project[contained_subprojects]', $project[count])";
						}
						else
						{
							$sql = "insert into acme_project_hierarchy (upload_fk, uploadtree_fk, pfile_fk, acme_project_fk, parent, contains_subproject_flag, contained_subprojects, count) values ($upload_pk, 0, $project[pfile_fk], $project[acme_project_pk], nullif('$project[parent]','')::integer, $project[contains_subproject_flag], '$project[contained_subprojects]', $project[count])";
						}
				  $insertResult = pg_query($PG_CONN, $sql);
				  DBCheckResult($insertResult, $sql, __FILE__, __LINE__);
				  pg_free_result($insertResult);
				}
			pg_free_result($result);
			}
    		//if hierarchy records are found, get hierarchy information
    
			$acme_project_hierarchy = array();
			
			$uploadtree_pk = $uploadtreepkSet;
		  if (empty($uploadtree_pk))
		  {
			$sql = "select acme_project_hierarchy.*, acme_project.project_name from acme_project_hierarchy left join acme_project on acme_project_pk = acme_project_fk where upload_fk='$upload_pk' and parent is null";
		  $result = pg_query($PG_CONN, $sql);
		  DBCheckResult($result, $sql, __FILE__, __LINE__);
		  $Row = pg_fetch_assoc($result);
		  pg_free_result($result);
		  if(empty($Row))
		  {
			//create dummy record for top of tree
			$sql = "select acme_project_hierarchy.*, acme_project.project_name from acme_project_hierarchy left join acme_project on acme_project_pk = acme_project_fk where upload_fk='$upload_pk' order by acme_project_hierarchy_pk limit 1";
			  $result = pg_query($PG_CONN, $sql);
			  DBCheckResult($result, $sql, __FILE__, __LINE__);
			  $Row = pg_fetch_assoc($result);
			  pg_free_result($result);
			  $Row['uploadtree_fk'] = $Row['parent'];
			$Row['parent'] = "";
			$Row['contains_subproject_flag'] = 1;
			$Row['count'] = 0;
		  }
	      //add root package
				if(empty($Row['parent']))
	      {
	      	if(empty($Row['project_name']))
	      	{
	      		$Row['project_name'] = $ufile_name;
	      	}
	      	else
	      	{
		      	$Row['project_name'] = $ufile_name."(".$Row['project_name'].")";
		      }
	      }
	      array_unshift($acme_project_hierarchy, $Row);
	      // get acme_project information for output if it has not been retrieved
	      if(empty($acme_project_array) && $Row['contains_subproject_flag'])
	      {
	      	
	      	$sql = "select acme_project.*, acme_project_hierarchy.* from acme_project,acme_project_hierarchy where acme_project_hierarchy.acme_project_fk=acme_project_pk and upload_fk='$Row[upload_fk]' and parent = ($Row[uploadtree_fk])  and uploadtree_fk <> 0";
		      $projresult = pg_query($PG_CONN, $sql);
		      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
		      $projectRow = pg_fetch_all($projresult);
	 	      pg_free_result($projresult);
	 	      if(!empty($projectRow))
	 	      {
	 	      	$acme_project_array = $projectRow;
	 	      }
	 	      else
	 	      {
	 	      
		      	$sql = "select acme_project.*, acme_project_hierarchy.* from acme_project,acme_project_hierarchy where acme_project_hierarchy.acme_project_fk=acme_project_pk and upload_fk='$Row[upload_fk]' and parent = ($Row[uploadtree_fk])  and uploadtree_fk = 0";
			      $projresult = pg_query($PG_CONN, $sql);
			      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
			      $acme_project_array = pg_fetch_all($projresult);
			      pg_free_result($projresult);
			    }
	      }
		  }
		  else
		  {
				if(empty($parent))
				{
					$sql = "select acme_project_hierarchy.*, acme_project.project_name  from acme_project_hierarchy, acme_project where upload_fk='$upload_pk' and parent is null and acme_project_pk = acme_project_fk";
				}
				else
				{
					$sql = "select acme_project_hierarchy.*, acme_project.project_name  from acme_project_hierarchy, acme_project where upload_fk='$upload_pk' and parent='$parent' and pfile_fk='$pfile_fk' and acme_project_pk = acme_project_fk";
				}
				$result = pg_query($PG_CONN, $sql);
		    DBCheckResult($result, $sql, __FILE__, __LINE__);
		    if (pg_num_rows($result) > 0) 
		    {
		    	$Row = pg_fetch_assoc($result);
		    	array_unshift($acme_project_hierarchy, $Row);
		    	$uploadtree_pk = $Row['parent'];
		    	$hierarchy_pk = $Row['acme_project_hierarchy_pk'];
		    	// get acme_project information for output if it has not been retrieved
		      if(empty($acme_project_array) && $Row['contains_subproject_flag'])
		      {
		      	$sql = "select acme_project.*, acme_project_hierarchy.* from acme_project,acme_project_hierarchy where acme_project_hierarchy.acme_project_fk=acme_project_pk and upload_fk='$Row[upload_fk]' and parent in($Row[contained_subprojects]) and uploadtree_fk = 0";
			      $projresult = pg_query($PG_CONN, $sql);
			      DBCheckResult($projresult, $sql, __FILE__, __LINE__);
			      $acme_project_array = pg_fetch_all($projresult);
			      pg_free_result($projresult);
		      }
		      
		    	while (!empty($uploadtree_pk))
				  {
				    $sql = "select acme_project_hierarchy.*, acme_project.project_name  from acme_project_hierarchy left join acme_project on acme_project_pk = acme_project_fk where upload_fk='$upload_pk' and contained_subprojects like '%$uploadtree_pk%' and acme_project_hierarchy_pk < $hierarchy_pk";
				    $hierarchyResult = pg_query($PG_CONN, $sql);
				    DBCheckResult($hierarchyResult, $sql, __FILE__, __LINE__);
				    $Row = pg_fetch_assoc($hierarchyResult);
				    if(empty($Row))
			      {
			      	//create dummy record for top of tree
			      	$Row['acme_project_hierarchy_pk'] = 1;
			      	$Row['upload_fk'] = $upload_pk;
			      	$Row['pfile_fk'] = 0;
			      	$Row['parent'] = "";
			      	$Row['contains_subproject_flag'] = 1;
			      	$Row['count'] = 0;
			      }
				    //add root package
						if(empty($Row['parent']))
			      {
			      	if(empty($Row['project_name']))
			      	{
			      		$Row['project_name'] = $ufile_name;
			      	}
			      	else
			      	{
				      	$Row['project_name'] = $ufile_name."(".$Row['project_name'].")";
				      }
			      }
			      $hierarchy_pk = $Row['acme_project_hierarchy_pk'];
				    pg_free_result($hierarchyResult);
				    if(!empty($Row))
				    {
					    array_unshift($acme_project_hierarchy, $Row);
					  }
				    $uploadtree_pk = $Row['parent'];
				  }
				  
		    }
		    pg_free_result($result);
		  }
		}
		
    $acme_project_array_orig = $acme_project_array;  // save the original state so we know which records to update

    /* If the save or spdx buttons were clicked, update $acme_project_array and save the data in the acme_upload table */
    if (!empty($savebtn) or !empty($spdxbtn))
    {
      /* First set all projects include to false */
      foreach ($acme_project_array as &$project)
      { 
        $project['include'] = 'f';
      }
      /* Now turn on projects include to match form */
      if (array_key_exists('includeproj', $_POST)) 
      {
        $includeArray = $_POST['includeproj'];
        foreach ($acme_project_array as &$project)
        { 
          if (array_key_exists($project['acme_project_fk'], $includeArray)) $project['include'] = "t";
        }
      }

      /* Finally, update the db with any changed include states */
      $NumRecs = count($acme_project_array);
      for ($i=0; $i<$NumRecs; $i++)
      { 
        $project = $acme_project_array[$i];
        $project_orig = $acme_project_array_orig[$i];
        if ($project['include'] != $project_orig['include'])
        {
          $include = $project['include'] ? "true" : "false";
          $sql = "update acme_upload set include='$include' where acme_upload_pk='$project[acme_upload_pk]'";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          pg_free_result($result);
        }
      }
    }

    /* aggregate the fossology licenses for each pfile and each acme_project */
    if ($folic)
    {
      foreach ($acme_project_array as &$project)
      {
        $sql = "select uploadtree_pk from acme_pfile, uploadtree where acme_project_fk=$project[acme_project_fk] 
                and acme_pfile.pfile_fk=uploadtree.pfile_fk and uploadtree.upload_fk=$upload_pk";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $LicArray = array();
        $ItemLicArray = array();
        while ($acme_pfileRow = pg_fetch_assoc($result))
        {
          $LicArray = GetFileLicenses($agent_pk, '', $acme_pfileRow['uploadtree_pk'], $uploadtree_tablename);
          foreach($LicArray as $key=>$license) $ItemLicArray[$key] = $license;
        }
        $project['licenses'] = '';
        foreach($ItemLicArray as $license) 
        {
          if ($license == "No_license_found") continue;
          if (!empty($project['licenses'])) $project['licenses'] .= ", ";
          $project['licenses'] .= $license;
        }
      }
    }

    /* sort $acme_project_array by count desc */
    usort($acme_project_array, 'proj_cmp');

    /* generate and download spdx file */
    if (!empty($spdxbtn))
    {
      $spdxfile = $this->GenerateSPDX($acme_project_array);
      $rv = DownloadString2File($spdxfile, "SPDX.rdf file", "xml");
      if ($rv !== true) echo $rv;
    }
    
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $this->NoHeader = 0;
        $this->OutputOpen("HTML", 1);
        $V .= $this->HTMLForm($acme_project_array, $upload_pk, $acme_project_hierarchy);
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  } // Output()

};
return;  // prevent anyone from seeing this plugin
$NewPlugin = new acme_review;
$NewPlugin->Initialize();
?>


