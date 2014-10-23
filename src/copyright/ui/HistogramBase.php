<?php
use Fossology\Lib\Dao\CopyrightDao;

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

class HistogramBase extends FO_Plugin {
  protected $agentName;
  /** @var  string */
  protected $viewName;

  /**  @var string */
  private $uploadtree_tablename;

  /**  @var CopyrightDao */

  private  $copyrightDao;

  function __construct()
  {
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->copyrightDao = $container->get('dao.copyright');
    $this->renderer = $container->get('twig.environment');

    $this->vars['name']=$this->Name;

  }

  /**
   * @param $type
   * @param $description
   * @param $uploadId
   * @param $uploadTreeId
   * @param $filter
   * @param $agentId
   * @return string
   */
  protected function getTableForSingleType($type, $description, $uploadId, $uploadTreeId, $filter, $agentId){


    $columns = '[
      { "sTitle" : "'._("Count").'", "sClass": "right read_only" ,"sWidth" : "5%" },
      { "sTitle" : "'.$description.'", "sClass": "left"},
      { "sTitle" : "", "sClass" : "center read_only", "sWidth" : "10%", "bSortable" : false }
    ]';
    $sorting = json_encode($this->returnSortOrder());

    $out=array("type"=> $type, "columns" => $columns, "sorting" =>$sorting, "uploadId" => $uploadId,
        "uploadTreeId" => $uploadTreeId, "agentId" => $agentId, "filter" =>$filter);

    //TODO template this! For now I just template the js
    $output = "<div><table border=1 width='100%' id='copyright".$type."'></table></div><br/>";

    return array($output,$out );
  }


  /**
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $filter
   * @param $Agent_pk
   * @param $VF
   * @return string
   */
  protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $Agent_pk, $VF)
  {
  }

  /**
   * \brief Given an $Uploadtree_pk, display: \n
   * (1) The histogram for the directory BY LICENSE. \n
   * (2) The file listing for the directory.
   *
   * \param $Uploadtree_pk
   * \param $Uri
   * \param $filter
   * \param $uploadtree_tablename
   * \param $Agent_pk - agent id
   */
  protected function ShowUploadHist($upload_pk, $Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
  {
    $V=""; // total return value

    list($ChildCount, $VF) = $this->getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk);
    $this->vars['childcount'] = $ChildCount;
    $this->vars['fileListing'] = $VF;

    //TODO
    /***************************************
    Problem: $ChildCount can be zero!
    This happens if you have a container that does not
    unpack to a directory.  For example:
    file.gz extracts to archive.txt that contains a license.
    Same problem seen with .pdf and .Z files.
    Solution: if $ChildCount == 0, then just view the license!
    $ChildCount can also be zero if the directory is empty.
     ***************************************/
    if ($ChildCount == 0)
    {
      $isADirectory = $this->isADirectory($Uploadtree_pk);
      if ($isADirectory) {
        return;
      }
      global $Plugins;
      $ModLicView = &$Plugins[plugin_find_id($this->viewName)];
      return($ModLicView->Output() );
    }

    $V = $this->fillTables($upload_pk, $Uploadtree_pk, $filter, $Agent_pk, $VF);

    return($V);
  } // ShowUploadHist()



  function OutputOpen()
  {

    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    return parent::OutputOpen();
  }

  protected function htmlContent()
  {
    $OutBuf="";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $filter = GetParm("filter",PARM_STRING);

    /* check upload permissions */
    $UploadPerm = GetUploadPerm($Upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    /* Get uploadtree_tablename */
    $uploadtree_tablename = GetUploadtreeTableName($Upload);
    $this->uploadtree_tablename = $uploadtree_tablename;

//     $OutBuf .= "<font class='text'>\n";

    /************************/
    /* Show the folder path */
    /************************/

    $this->vars['dir2browse'] =  Dir2Browse($this->Name,$Item,NULL,1,"Browse",-1,'','',$uploadtree_tablename);
    if (!empty($Upload))
    {
      /** advanced interface allowing user to select dataset (agent version) */
      $Agent_name = $this->agentName;
      $dataset = $this->agentName."_dataset";
      $arstable = $this->agentName."_ars";
      /** get proper agent_id */
      $Agent_pk = GetParm("agent", PARM_INTEGER);
      if (empty($Agent_pk))
      {
        $Agent_pk = LatestAgentpk($Upload, $arstable);
      }

      if ($Agent_pk == 0)
      {
        /** schedule copyright */
        $OutBuf .= ActiveHTTPscript("Schedule");
        $OutBuf .= "<script language='javascript'>\n";
        $OutBuf .= "function Schedule_Reply()\n";
        $OutBuf .= "  {\n";
        $OutBuf .= "  if ((Schedule.readyState==4) && (Schedule.status==200))\n";
        $OutBuf .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
        $OutBuf .= "  }\n";
        $OutBuf .= "</script>\n";

        $OutBuf .= "<form name='formy' method='post'>\n";
        $OutBuf .= "<div id='msgdiv'>\n";
        $OutBuf .= _("No data available.");
        $OutBuf .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
        $OutBuf .= "onClick='Schedule_Get(\"" . Traceback_uri() . "?mod=schedule_agent&upload=$Upload&agent=agent_copyright \")'>\n";
        $OutBuf .= "</input>";
        $OutBuf .= "</div> \n";
        $OutBuf .= "</form>\n";

      }

      else
      {
        $AgentSelect = AgentSelect($Agent_name, $Upload, $dataset, $Agent_pk, "onchange=\"addArsGo('newds', 'copyright_dataset');\"");

        /** change the copyright  result when selecting one version of copyright */
        if (!empty($AgentSelect))
        {
          $action = Traceback_uri() . "?mod=copyrighthist&upload=$Upload&item=$Item";

          $OutBuf .= "<script type='text/javascript'>
            function addArsGo(formid, selectid)
            {
              var selectobj = document.getElementById(selectid);
              var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
              document.getElementById(formid).action='$action'+'&agent='+Agent_pk;
              document.getElementById(formid).submit();
              return;
            }
          </script>";

          /* form to select new dataset, show dataset */
          $OutBuf .= "<form action='$action' id='newds' method='POST'>\n";
          $OutBuf .= $AgentSelect;
          $OutBuf .= "</form>";
        }

        $Uri = preg_replace("/&item=([0-9]*)/", "", Traceback());

        /* Select list for filters */
        $SelectFilter = "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$Upload, $Item)'>";

        $Selected = ($filter == 'legal') ? "selected" : "";
        $text = _("Show all");
        $SelectFilter .= "<option $Selected value='all'>$text";

        $text = _("Show all legal copyrights");
        $SelectFilter .= "<option $Selected value='legal'>$text";

        $text = _("Show files without licenses");
        $Selected = ($filter == 'nolics') ? "selected" : "";
        $SelectFilter .= "<option $Selected value='nolics'>$text";

        $SelectFilter .= "</select>";
        $OutBuf .= $SelectFilter;

        list($tables, $tableVars) = $this->ShowUploadHist($Upload, $Item, $Uri, $filter, $uploadtree_tablename, $Agent_pk);
        $this->vars['tables'] = $tableVars;
        $OutBuf .= $tables;
      }
    }
    $OutBuf .= "</font>\n";


    $this->vars['scriptBlock'] = $this->createScriptBlock();
    $this->vars['pageContent'] = $OutBuf;

    return;
  }



  /**
   * @param $Uploadtree_pk
   * @param $Uri
   * @param $uploadtree_tablename
   * @param $Agent_pk
   * @param $upload_pk
   * @return array
   */
  protected function getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk)
  {
    $VF=""; // return values for file listing
    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $uploadtree_tablename);
    $ChildCount = 0;
    $ChildLicCount = 0;
    $ChildDirCount = 0; /* total number of directory or containers */
    foreach ($Children as $C)
    {
      if (Iscontainer($C['ufile_mode']))
      {
        $ChildDirCount++;
      }
    }

    $VF .= "<table border=0>";
    foreach ($Children as $C)
    {
      if (empty($C))
      {
        continue;
      }

      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);
      $ModLicView = &$Plugins[plugin_find_id($this->viewName)];
      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=view-license&agent=$Agent_pk&upload=$upload_pk&item=$C[uploadtree_pk]";
      } else
      {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
      if (Iscontainer($C['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk'], $uploadtree_tablename);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      } else
      {
        $LicUri = NULL;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $LicCount = 0;

      $VF .= "<tr><td id='$C[uploadtree_pk]' align='left'>";
      $HasHref = 0;
      $HasBold = 0;
      if ($IsContainer)
      {
        $VF .= "<a href='$LicUri'>";
        $HasHref = 1;
        $VF .= "<b>";
        $HasBold = 1;
      } else if (!empty($LinkUri)) //  && ($LicCount > 0))
      {
        $VF .= "<a href='$LinkUri'>";
        $HasHref = 1;
      }
      $VF .= $C['ufile_name'];
      if ($IsDir)
      {
        $VF .= "/";
      };
      if ($HasBold)
      {
        $VF .= "</b>";
      }
      if ($HasHref)
      {
        $VF .= "</a>";
      }
      $VF .= "</td><td>";

      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount, 0, "", ",") . "&nbsp;";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
        $ChildLicCount += $LicCount;
      }
      $VF .= "</td>";
      $VF .= "</tr>\n";

      $ChildCount++;
    }
    $VF .= "</table>\n";
    return array($ChildCount, $VF);
  }

  /**
   * @param $Uploadtree_pk
   * @return bool
   */
  protected function isADirectory($Uploadtree_pk)
  {
    global $PG_CONN;
    $sql = "SELECT * FROM $this->uploadtree_tablename WHERE uploadtree_pk = '$Uploadtree_pk';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $isADirectory = IsDir($row['ufile_mode']);
    return $isADirectory;
  }


  public function returnSortOrder () {
    $defaultOrder = array (
        array(0, "desc"),
        array(1, "desc"),
    );
    return $defaultOrder;
  }




  public function getTemplateName()
  {
    return "copyrighthist.html.twig";
  }

  protected  function createScriptBlock()
  {
  }


}