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

define("TITLE_acme_files", _("ACME File List"));

class acme_files extends FO_Plugin
{
  var $Name       = "acme_files";
  var $Title      = TITLE_acme_files;
  var $Version    = "1.0";
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoHTML  = 1;  // prevent the http header from being written in case we have to download a file
  var $showIncludeFlag = 0; //display "include" check box column: 0 - hidden; 1 - displayed

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
  	global $PG_CONN;
    if (GetParm("mod", PARM_STRING) == $this->Name) 
    {
      $detail = GetParm("detail",PARM_INTEGER);
      $pfile_fk = GetParm("item",PARM_INTEGER);
	    $parent = GetParm("parent",PARM_INTEGER);
      //0: redirect from ACME High Level Review, including multi projects; 1: redirect from ACME Low Level Review, including single projects
      if ($detail)
      {
        $text = _("ACME Low Level Review");
        $URI = "acme_review" . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=1";
      }
      else
      {
      	$text = _("ACME High Level Review");
        $URI = "acme_review" . Traceback_parm_keep(array( "page", "upload", "folic")) . "&detail=0&item=$pfile_fk&parent=$parent";
      }

      // micro menu when in acme_review
      menu_insert("acme::$text", 1, $URI, $text);
      $groupbySha = GetParm("groupbySha",PARM_INTEGER);
      //0: show all; 1: group by sha1(pfile_id) value
      if ($groupbySha)
      {
        $text = _("Show All");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic", "acme_project", "project_name")) . "&detail=$detail&groupbySha=0&item=$pfile_fk&parent=$parent";
      }
      else
      {
        $text = _("Group By Sha1 Value");
        $URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "folic", "acme_project", "project_name")) . "&detail=$detail&groupbySha=1&item=$pfile_fk&parent=$parent";
      }
      // micro menu when in acme_review
      menu_insert("acme::$text", 1, $URI, $text);
    }
  } // RegisterMenus()
/** 
   * \brief create the HTML to display the form showing the file list in found project
   * \param $acme_file_array
   * \param $upload_pk
   * \return HTML to display results
   */
  function HTMLForm($acme_file_array, $upload_pk)
  {
    $Outbuf = "";
    $uploadtreeRec = GetSingleRec("uploadtree", "where upload_fk=$upload_pk and parent is null");
    $Outbuf .= Dir2Browse($this->Name,$uploadtreeRec['uploadtree_pk'], NULL, 1, "acme");
    $Outbuf .= "<p>";

    $URI = "?mod=" . $this->Name . Traceback_parm_keep(array( "page", "upload", "folic", "detail", "acme_project", "project_name", "groupbySha", "item", "parent"));
    $Outbuf .= "<form action='" . $URI . "' method='POST'>\n";
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
    $groupbySha = GetParm("groupbySha",PARM_INTEGER);
    $groupbySha = empty($groupbySha) ? 0 : 1;
	if ($detail)
	{
		$text = "Project Name: ".GetParm("project_name",PARM_TEXT);
	$Outbuf .= "<p>$text</p>";
	}
    $Outbuf .= "<table border=1 width='100%'>";
    $Outbuf .= "<tr>";
    if ($showIncludeFlag)
	{
		$text = _('Include');
		$Outbuf .= "<th>$text</th>";
	}
    $text = _('File Name');
    $Outbuf .= "<th>$text</th>";
    
    $text = _('FOSSology License');
    $Outbuf .= "<th>$text</th>";
    if (!$detail)
	{
		$text = _('Project Name<br/>(Antelink)');
		$Outbuf .= "<th>$text</th>";
	}
    $text = _('Antelink License');
    $Outbuf .= "<th>$text</th>";
    $text = _('Note');
    $Outbuf .= "<th>$text</th>";
    $Outbuf .= "</tr>";

    /* For alternating row background colors */
    $RowStyle1 = "style='background-color:lavender'";
    $RowStyle2 = "style='background-color:lightyellow'";
    $ColorSpanRows = 1;  // Alternate background color every $ColorSpanRows
    $RowNum = 0;

    if (empty($acme_file_array)) $acme_file_array = array(); 
    $RowNum = count($acme_file_array);
    $Outbuf .= "$RowNum rows found<br>";
    $RowNum = 0;
    foreach ($acme_file_array as $file)
    {
      /* Set alternating row background color - repeats every $ColorSpanRows rows */
      $RowStyle = (($RowNum++ % (2*$ColorSpanRows))<$ColorSpanRows) ? $RowStyle1 : $RowStyle2;

      $Outbuf .= "<tr $RowStyle>";
      if ($showIncludeFlag)
      {
	      $Checked = $file['include'] == 't' ? "checked=\"checked\"" : '' ;
	      $Outbuf .= "<td><input type='checkbox' name='includefile[$file[pfile_id]]' $Checked></td>\n";
	    }

      $Outbuf .= "<td>$file[file_name]</td>";
      $Outbuf .= "<td nowrap>$file[fossology_lics]</td>";
      if (!$detail)
			{
				$Outbuf .= "<td>$file[project_name]</td>";
			}
      $Outbuf .= "<td nowrap>$file[antelink_lics]</td>";
      $Outbuf .= "<td nowrap>$file[note]</td>";
      $Outbuf .= "</tr>";
    }
    $Outbuf .= "</table>";

    $Outbuf .= "$RowNum rows found<br>";
		if ($showIncludeFlag)
		{
	    $text = _("Save");
	    $Outbuf .= "<input type='submit' value='$text' name='savebtn'>\n";
	  }
    $Outbuf .= "</form>\n";
    /*******  END Input form  *******/
    
    return $Outbuf;
  }
	function FindACMEFiles($fileRow, $path, &$file_array)
  {
    global $PG_CONN;

    /* See if the current file is not a container/directory */
    $lft = $fileRow[lft];
    $rgt = $fileRow[rgt];
    $fileRow[ufile_name] = $path.$fileRow[ufile_name];
   	$inx = count($file_array);
   	$file_array[$inx] = $fileRow;
    if ($rgt - $lft >1)
    {
    	/* check each child */
			$sql = "select uploadtree_pk,uploadtree.pfile_fk as pfile_fk,ufile_name,licenses,project_name,lft,rgt,upload_fk from 
							(select * from uploadtree where uploadtree.upload_fk=$fileRow[upload_fk] and lft>$lft and rgt<$rgt 
							and pfile_fk!=0 and ((ufile_mode & (1<<28))=0)) as uploadtree
							left join (select project_name,licenses, pfile_fk from acme_pfile, acme_project where acme_pfile.acme_project_fk=acme_project.acme_project_pk) as acme
							on uploadtree.pfile_fk = acme.pfile_fk";
      $childrenResult = pg_query($PG_CONN, $sql);
      DBCheckResult($childrenResult, $sql, __FILE__, __LINE__);
      while ($child = pg_fetch_assoc($childrenResult))
      {
        $this->FindACMEFiles($child, $fileRow[ufile_name]."/", $file_array);
      }
      pg_free_result($childrenResult);
    }

    return;
  } // FindACMEFiles()
  /** borrow from GetFileLicenses
	 * \file common-license-file.php
	 * \brief This file contains common functions for the
	 * license_file and license_ref tables.
	 */
	
	/**
	 * \brief get all the licenses for a single file or uploadtree
	 * 
	 * \param $agent_pk - agent id
	 * \param $pfile_pk - pfile id, (if empty, $uploadtree_pk must be given)
	 * \param $uploadtree_pk - (used only if $pfile_pk is empty)
	 * \param $uploadtree_tablename
	 * \param $duplicate - get duplicated licenses or not, if NULL: No, or Yes
	 * 
	 * \return Array of file licenses   LicArray[fl_pk] = rf_fullname if $duplicate is Not NULL
	 * LicArray[rf_pk] = rf_fullname if $duplicate is NULL
	 * FATAL if neither pfile_pk or uploadtree_pk were passed in
	 */
	function GetFileLicensesFullname($agent_pk, $pfile_pk, $uploadtree_pk, $uploadtree_tablename='uploadtree', $duplicate="")
	{
	  global $PG_CONN;

	  if (empty($agent_pk)) Fatal("Missing parameter: agent_pk", __FILE__, __LINE__);
	
	  if ($uploadtree_pk)
	  {
	    /* Find lft and rgt bounds for this $uploadtree_pk  */
	    $sql = "SELECT lft, rgt, upload_fk FROM $uploadtree_tablename
	                   WHERE uploadtree_pk = $uploadtree_pk";
	    $result = pg_query($PG_CONN, $sql);
	    DBCheckResult($result, $sql, __FILE__, __LINE__);
	    $row = pg_fetch_assoc($result);
	    $lft = $row["lft"];
	    $rgt = $row["rgt"];
	    $upload_pk = $row["upload_fk"];
	    pg_free_result($result);
	
	    /*  Get the licenses under this $uploadtree_pk*/
	    $sql = "SELECT distinct(rf_fullname) as rf_fullname, rf_pk as rf_fk, fl_pk, rf_shortname
	              from license_file_ref,
	                  (SELECT distinct(pfile_fk) as PF from $uploadtree_tablename 
	                     where upload_fk=$upload_pk 
	                       and lft BETWEEN $lft and $rgt) as SS
	              where PF=pfile_fk and agent_fk=$agent_pk
	              order by rf_fullname asc";
	    $result = pg_query($PG_CONN, $sql);
	    DBCheckResult($result, $sql, __FILE__, __LINE__);
	  }
	  else Fatal("Missing function inputs", __FILE__, __LINE__);
	
	  $LicArray = array();
	  if ($duplicate)  // get duplicated licenses
	  {
	    while ($row = pg_fetch_assoc($result))
	    {
	    	if(empty($row['rf_fullname']))
	    	{
	    		$LicArray[$row['fl_pk']] = $row['rf_shortname'];
	    	}
	    	else
	    	{
		      $LicArray[$row['fl_pk']] = $row['rf_fullname'];
		    }
	    }
	  } else { // do not return duplicated licenses
	    while ($row = pg_fetch_assoc($result))
	    {
	    	if(empty($row['rf_fullname']))
	    	{
	    		$LicArray[$row['rf_fk']] = $row['rf_shortname'];
	    	}
	    	else
	    	{
		      $LicArray[$row['rf_fk']] = $row['rf_fullname'];
		    }
	    }
	  }
	  pg_free_result($result);
	  return $LicArray;
	}
	/** borrow from Dir2Browse@common-dir.php
	 * \brief Get an html linked string of a file browse path.
	 *
	 * \param $Mod - Module name (e.g. "browse")
	 * \param $UploadtreePk
	 * \param $uploadtree_tablename
	 *
	 * \return string of browse paths
	 */
	function Dir2BrowseForAcme($Mod, $UploadtreePk, $uploadtree_tablename="uploadtree")
	{
	  $Uri = Traceback_uri() . "?mod=$Mod";

	  /* Get array of upload recs for this path, in top down order.
	   This does not contain artifacts.
	   */
	  $LinkLast = NULL;
	  $Path = Dir2Path($UploadtreePk, $uploadtree_tablename);
	  $Last = &$Path[count($Path)-1];
	  $V .= "<font class='text'>\n";
	  $FirstPath=1; /* every firstpath belongs on a new line */
	  /* Show the path within the upload */
	  if ($FirstPath!=0)
	  {
	    for($p=0; !empty($Path[$p]['uploadtree_pk']); $p++)
	    {
	      $P = &$Path[$p];
	      if (empty($P['ufile_name'])) { continue; }
	      $UploadtreePk = $P['uploadtree_pk'];
	
	      if (!$FirstPath) { $V .= "/ "; }
	      if (!empty($LinkLast) || ($P != $Last))
	      {
	        if ($P == $Last)
	        {
	          $Uri = Traceback_uri() . "?mod=$LinkLast";
	        }
	        $V .= "<a href='$Uri&upload=" . $P['upload_fk'] . $Opt . "&item=" . $UploadtreePk . "'>";
	      }
	
	      if (Isdir($P['ufile_mode']))
	      {
	        $V .= $P['ufile_name'];
	      }
	      else
	      {
	        if (!$FirstPath && Iscontainer($P['ufile_mode']))
	        {
	          $V .= "<br>\n&nbsp;&nbsp;";
	        }
	        $V .= "<b>" . $P['ufile_name'] . "</b>";
	      }
	
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

    $CriteriaCount = 0;
    $V="";
    $GETvars="";
    $upload_pk = GetParm("upload",PARM_INTEGER);
    $acme_project_pk = GetParm("acme_project",PARM_INTEGER);
    $savebtn = GetParm("savebtn",PARM_RAW);
		$acme_file_array = array(); 
    $agent_pk = LatestAgentpk($upload_pk, "nomos_ars");
    $folic = 1;
    $acme_file_array = array();
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
    $groupbySha = GetParm("groupbySha",PARM_INTEGER);
    $groupbySha = empty($groupbySha) ? 0 : 1;
    $parent = GetParm("parent",PARM_INTEGER);
    if (empty($agent_pk))
    {
      echo "Missing fossology license data.  Run a license scan on this upload.<br>";
      exit;
    }
    $uploadtree_tablename = GetUploadtreeTableName($upload_pk);
    /* aggregate the fossology licenses and antelink licenses for each pfile*/
    if ($folic)
    {
		if($detail)
		{   
			//low level
			$sql = "select uploadtree_pk,uploadtree.pfile_fk as pfile_fk,ufile_name,licenses from acme_pfile, acme_project, uploadtree where acme_project_fk=$acme_project_pk 
							and acme_pfile.pfile_fk=uploadtree.pfile_fk and uploadtree.upload_fk=$upload_pk
							and acme_pfile.acme_project_fk=acme_project.acme_project_pk";
			$result = pg_query($PG_CONN, $sql);
			DBCheckResult($result, $sql, __FILE__, __LINE__);
			$acme_pfiles = array();
			$inx = 0;
			while ($row = pg_fetch_assoc($result))
			  {
				$acme_pfiles[$inx] = $row;
				$inx++;
			  }
			  pg_free_result($result);
		  }
		  else
		  {
			//high level
			$acme_pfiles = array();
			
			$sql = "select uploadtree_pk,uploadtree.pfile_fk as pfile_fk,ufile_name,licenses,project_name,uploadtree.lft,uploadtree.rgt,upload_fk  from acme_pfile, acme_project, uploadtree,
			(select lft, rgt from uploadtree where upload_fk=$upload_pk and uploadtree_pk=$parent) as P 
					where acme_project_fk=$acme_project_pk 
							and acme_pfile.pfile_fk=uploadtree.pfile_fk and uploadtree.upload_fk=$upload_pk
							and acme_pfile.acme_project_fk=acme_project.acme_project_pk
							and uploadtree.lft>P.lft and uploadtree.rgt<P.rgt";
				$resultProjects = pg_query($PG_CONN, $sql);
				DBCheckResult($resultProjects, $sql, __FILE__, __LINE__);
					while ($row = pg_fetch_assoc($resultProjects))
					{
						$this->FindACMEFiles($row,"",$acme_pfiles);
					}
					pg_free_result($resultProjects);
		  }
      $LicArray = array();
      $ItemLicArray = array();
      $amountOfFiles = count($acme_pfiles);
      for ($i=0; $i< $amountOfFiles; $i++)
      {
      	$acme_pfileRow = $acme_pfiles[$i];
      	if($groupbySha)
	      {
		      $acme_file_index = $acme_pfileRow['pfile_fk'];
		    }
		    else
		    {
		    	$acme_file_index = $acme_pfileRow['uploadtree_pk'];
		    }
		    if ($acme_pfileRow['pfile_fk'] == $acme_file_array[$acme_file_index]['pfile_id'])
		    {
		    	continue;
		    }
        $LicArray = $this->GetFileLicensesFullname($agent_pk, '', $acme_pfileRow['uploadtree_pk'], $uploadtree_tablename);
        foreach($LicArray as $key=>$license) $ItemLicArray[$key] = $license;
        $fossology_lics = '';
	      foreach($ItemLicArray as $license) 
	      {
	        if ($license == "No_license_found") continue;
	        if (!empty($fossology_lics)) $fossology_lics .= "|";
	        $fossology_lics .= $license;
	      }
	      unset($ItemLicArray);
	      if (empty($fossology_lics))
	      {
	      	$fossology_lics = "No_license_found";
	      }

 	      $acme_file_array[$acme_file_index]['pfile_id'] = $acme_pfileRow['pfile_fk'];
 	      if(!$detail)
 	      {
			//high level
		    $acme_file_array[$acme_file_index]['project_name'] = $acme_pfileRow['project_name'];
		  }
          $acme_file_array[$acme_file_index]['file_name'] = $this->Dir2BrowseForAcme("browse", $acme_pfileRow['uploadtree_pk'], $uploadtree_tablename);
				
	      $fossology_liclist = explode("|", $fossology_lics);
	      sort($fossology_liclist);
	      $fossology_lic_sorted = implode("<br/>", $fossology_liclist);
 	      $acme_file_array[$acme_file_index]['fossology_lics'] = $fossology_lic_sorted;
 	      $acme_liclist = explode("|", $acme_pfileRow['licenses']);
	      sort($acme_liclist);
	      $acme_lic_sorted = implode("<br/>", $acme_liclist);
 	      $acme_file_array[$acme_file_index]['antelink_lics'] = $acme_lic_sorted;
 	      if (empty($acme_pfileRow['licenses']))
 	      {
 	      	$acme_file_array[$acme_file_index]['note'] = 'no information in Antelink';
 	      }
 	      else
 	      {
 	      	//check if fossology scanning result include acme scanning result
 	      	$acme_licenses = explode("|", $acme_pfileRow['licenses']);
 	      	foreach($acme_licenses as $acme_license)
 	      	{
	 	      	$acme_license_fullname = explode("(", $acme_license);
	 	      	if (strpos($fossology_lics,trim($acme_license_fullname[0])) !== false)
	 	      	{
		 	      	$acme_file_array[$acme_file_index]['note'] = $acme_file_array[$acme_file_index]['note']."<br/>".$acme_license;
		 	      }
		 	    }
		 	    if(!empty($acme_file_array[$acme_file_index]['note']))
		 	    {
			 	    $acme_file_array[$acme_file_index]['note'] = "The following license are also found in AnteLink:".$acme_file_array[$acme_file_index]['note'];
			 	  }
 	      }

 	      if ($showIncludeFlag)
 	      {
	 	      $acme_file_array[$acme_file_index]['include'] = 'f';
	 	    }
      }
      
    }

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $this->NoHeader = 0;
        $this->OutputOpen("HTML", 1);
        $V .= $this->HTMLForm($acme_file_array, $upload_pk);
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
$NewPlugin = new acme_files;
$NewPlugin->Initialize();
?>

