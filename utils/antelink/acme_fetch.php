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

define("TITLE_acme_fetch", _("ACME Fetcher"));

class acme_fetch extends FO_Plugin
{
  var $Name       = "acme_fetch";
  var $Title      = TITLE_acme_fetch;
  var $Version    = "1.0";
  var $MenuList   = "";
  var $MenuOrder  = 110;
  var $Dependency = array("browse", "view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoHTML  = 1;  // prevent the http header from being written in case we have to download a file
  var $Status_Finished  = 0;
  var $Status_Fetching  = 1;
  var $Status_NotStarted  = 2;
  
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
			$status = $this->FetcheStatus($upload_pk);
			if($status==$this->Status_Finished)
			{
				$text = _("Click here to ACME Review");
				$URI = "acme_review" . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
			}
			else if ($status==$this->Status_Fetching)
			{
				$text = _("ACME is Fetching");	
				$URI = "";
			}
			else if ($status==$this->Status_NotStarted)
			{
				$text = _("ACME Fetch Starter");
				$URI = $this->Name . Traceback_parm_keep(array( "page", "upload", "detail")) . "&start=true";
			}
			else
			{
				$text = _("ACME Fetch Encounter Unknown Error");
				$URI = "browse" . Traceback_parm_keep(array( "page", "upload")) . "&detail=0";
			}
	
	    // micro menu when in acme_fetch
      menu_insert("acme::$text", 1, $URI, $text);
    }
  } // RegisterMenus()
  function FetcheStatus($upload_pk)
  {
    global $PG_CONN;
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
				return 0;
			}
			else
			{
				//fetching is not over
				return 1;
			}
		}
		else
		{
			//no log file found
			return 2;
		}
		//Not Available
		return -1;
  }
  /** 
   * \brief create the HTML to display the form showing the found projects
   * \param $acme_project_array
   * \param $upload_pke
   * \return HTML to display results
   */
  function HTMLForm($upload_pk, $startflag)
  {
		global $PG_CONN;

		$acme_logfile_precheck_progress = "log_acme_precheck_".$upload_pk;
		$progressPrecheck = exec('tail -1 /tmp/'.$acme_logfile_precheck_progress, $outputArr= array(), $return_var);
		if(!empty($progressPrecheck))
		{
			$progressPrechecks = split('/',$progressPrecheck);
			if(!empty($progressPrechecks[1]) && ($progressPrechecks[1] !=0))
			{
				$percentagePrecheck = (int)($progressPrechecks[0] / $progressPrechecks[1] * 100);
			}
		}
		
		
		$acme_logfile_tagged_progress = "log_acme_tagged_".$upload_pk;
		$progressTagged = exec('tail -1 /tmp/'.$acme_logfile_tagged_progress, $outputArr= array(), $return_var);
		if(!empty($progressTagged))
		{
			$progressTaggeds = split('/',$progressTagged);
			if(!empty($progressTaggeds[1]) && ($progressTaggeds[1] !=0))
			{
				$percentageTagged = (int)($progressTaggeds[0] / $progressTaggeds[1] * 100);
			}
		}
		$Outbuf = "";
		$Outbuf .= "<meta http-equiv='refresh' content='5' >";
		$Outbuf .= "<meta charset='utf-8'>";
	    $Outbuf .= "<link rel='stylesheet' href='//code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css'>";
		$Outbuf .= "<script src='//code.jquery.com/jquery-1.9.1.js'></script>";
		$Outbuf .= "<script src='//code.jquery.com/ui/1.10.4/jquery-ui.js'></script>";
		$Outbuf .= "<link rel='stylesheet' href='/resources/demos/style.css'>";
		$Outbuf .= "<script>";
		$Outbuf .= "$(function() {";
		if(!empty($percentagePrecheck))
		{
			$Outbuf .= "  $( '#progressbarprecheck' ).progressbar({";
			$Outbuf .= "    value: $percentagePrecheck";
			$Outbuf .= "  });";
		}
		if(!empty($percentageTagged))
		{
			$Outbuf .= "  $( '#progressbartagged' ).progressbar({";
			$Outbuf .= "    value: $percentageTagged";
			$Outbuf .= "  });";
		}
		$Outbuf .= "});";
		$Outbuf .= "</script>";
		
		$uploadtreeRec = GetSingleRec("uploadtree", "where upload_fk=$upload_pk and parent is null");
		$Outbuf .= Dir2Browse($this->Name,$uploadtreeRec['uploadtree_pk'], NULL, 1, "acme");
		$Outbuf .= "<p>";
		$Outbuf .= "<p><b>This page will be refreshed every 5 secs!</b></p>";
		$Outbuf .= "[ACME is Fetching] indicats system is fetching ACME data now;<br>";
		$Outbuf .= "[ACME Fetch Starter] indicats ACME data has not yet been fetched. You could click the link to start the fetching process;<br>";
		$Outbuf .= "[Click here to ACME Review] indicats ACME data has been fetched. You could click the link to access ACME Review page;<br>";
		
		$status = $this->FetcheStatus($upload_pk);
		if($status==$this->Status_Finished)
		{
			$text = _("Click here to ACME Review");
			$URI = "?mod=" . "acme_review" . Traceback_parm_keep(array( "page", "upload", "detail"));
		}
		else if (!empty($startflag) && ($status==$this->Status_NotStarted))
		{
			//get ufile name
			$sql = "select upload_desc, upload_filename from upload where upload_pk='$upload_pk'";
			$ufile_name_result = pg_query($PG_CONN, $sql);
			DBCheckResult($ufile_name_result, $sql, __FILE__, __LINE__);
			$Row = pg_fetch_assoc($ufile_name_result);
			$desc = $Row['upload_desc'];
			$name = $Row['upload_filename'];
			pg_free_result($ufile_name_result);
			//insert record to tag table
			$sql = "insert into tag (tag, tag_desc) values ('$name', '$desc')";
			$result = pg_query($PG_CONN, $sql);
			DBCheckResult($result, $sql, __FILE__, __LINE__);
			pg_free_result($result);
			//get inserted tag pk
			$sql = "select tag_pk from tag where tag='$name' and tag_desc='$desc' order by tag_pk desc limit 1";
			$result = pg_query($PG_CONN, $sql);
			DBCheckResult($result, $sql, __FILE__, __LINE__);
			$Row = pg_fetch_assoc($result);
			pg_free_result($result);
			$acme_logfile = "log_acme_".$upload_pk;
			$startFetch = exec('/etc/fossology/fo_antelink.php -v -u ' .$upload_pk.' -t ' .$Row['tag_pk']. ' > /tmp/'.$acme_logfile. ' &', $outputArr= array(), $return_var);
			if($return_var)
			{
				$text = _("ACME Fetch Encounter Unknown Error");
				$URI = "?mod=" . "browse" . Traceback_parm_keep(array( "page", "upload", "detail"));
			}
			else
			{
				$text = _("Fetch process has been started successfully, ACME is Fetching");	
				$URI = "";
			}
		}
		else if ($status==$this->Status_Fetching)
		{
			if(!empty($percentagePrecheck))
			{
				$Outbuf .= "<p><b>percentage of Precheck -->: $percentagePrecheck ($progressPrecheck)</b></p>";
				$Outbuf .= "<div id='progressbarprecheck'></div>";
			}
			if(!empty($percentageTagged))
			{
				$Outbuf .= "<p><b>percentage of file tagging -->: $percentageTagged ($progressTagged)</b></p>";
				$Outbuf .= "<div id='progressbartagged'></div>";
			}
			$text = _("ACME is Fetching");	
			$URI = "";
		}
		else if ($status==$this->Status_NotStarted)
		{
			$text = _("ACME Fetch Starter");
			$URI = "?mod=" . $this->Name . Traceback_parm_keep(array( "page", "upload", "detail")) . "&start=true";
		}
		else
		{
			$text = _("ACME Fetch Encounter Unknown Error");
			$URI = "?mod=" . "browse" . Traceback_parm_keep(array( "page", "upload", "detail"));
		}
			
		if(empty($URI))
		{
			$Outbuf .= "<p align='center' >$text</p>";
		}
		else
		{
			$ProjectURL = Traceback_uri() . $URI;
			$Outbuf .= "<p align='center' ><b><a href='$ProjectURL'>$text</a></b></p>";
		}
    return $Outbuf;
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
    $detail = GetParm("detail",PARM_INTEGER);
    $detail = empty($detail) ? 0 : 1;
	$startflag = GetParm("start",PARM_STRING);
   
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $this->NoHeader = 0;
        $this->OutputOpen("HTML", 1);
        $V .= $this->HTMLForm($upload_pk, $startflag);
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
$NewPlugin = new acme_fetch;
$NewPlugin->Initialize();
?>

