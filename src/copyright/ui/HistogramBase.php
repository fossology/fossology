<?php
/***********************************************************
 * Copyright (C) 2014-2017 Siemens AG
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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;

abstract class HistogramBase extends FO_Plugin {
  protected $agentName;
  /** @var  string */
  protected $viewName;
  /** @var string */
  private $uploadtree_tablename;
  /** @var UploadDao */
  private $uploadDao;
  
  function __construct()
  {
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;

    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
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
  public function getTableForSingleType($type, $description, $uploadId, $uploadTreeId, $filter, $agentId)
  {
    $sorting = json_encode($this->returnSortOrder());

    $out = array("type" => $type, "sorting" => $sorting, "uploadId" => $uploadId,
        "uploadTreeId" => $uploadTreeId, "agentId" => $agentId, "filter" => $filter, "description" => $description);

    //TODO template this! For now I just template the js
    $typeDescriptor = "";
    if($type !== "statement")
    {
      $typeDescriptor = $type;
    }
    $output = "<h4>Activated $typeDescriptor statements:</h4>
               <div><table border=1 width='100%' id='copyright".$type."'></table></div>
               <br/><br/>
               <div>
                <a style='cursor: pointer; margin-left:10px;'id='deleteSelected".$type."' class='buttonLink'>Mark selected rows for deletion</a>
               <br/><br/>
               <h4>Deactivated $typeDescriptor statements:</h4>
               </div>
               <div><table border=1 width='100%' id='copyright".$type."deactivated'></table></div>";

    return array($output, $out);
  }


  /**
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $filter
   * @param $agentId
   * @param $VF
   * @return string
   */
  abstract protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $agentId, $VF);

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
      $ModLicView = plugin_find($this->viewName);
      return $ModLicView->execute();
    }
    return $this->fillTables($upload_pk, $Uploadtree_pk, $filter, $Agent_pk, $VF);
  }


  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return 0;
    }
    return parent::OutputOpen();
  }

  public function Output()
  {
    $OutBuf="";
    $uploadId = GetParm("upload",PARM_INTEGER);
    $item = GetParm("item",PARM_INTEGER);
    $filter = GetParm("filter",PARM_STRING);

    /* check upload permissions */
    if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()))
    {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }

    /* Get uploadtree_tablename */
    $uploadtree_tablename = GetUploadtreeTableName($uploadId);
    $this->uploadtree_tablename = $uploadtree_tablename;

    /************************/
    /* Show the folder path */
    /************************/

    $this->vars['dir2browse'] =  Dir2Browse($this->Name,$item,NULL,1,"Browse",-1,'','',$uploadtree_tablename);
    if (empty($uploadId))
    {
      return 'no item selected';  
    }
    
    /** advanced interface allowing user to select dataset (agent version) */
    $dataset = $this->agentName."_dataset";
    $arstable = $this->agentName."_ars";
    /** get proper agent_id */
    $agentId = GetParm("agent", PARM_INTEGER);
    if (empty($agentId))
    {
      $agentId = LatestAgentpk($uploadId, $arstable);
    }

    if ($agentId == 0)
    {
      /** schedule copyright */
      $OutBuf .= ActiveHTTPscript("Schedule");
      $OutBuf .= "<script language='javascript'>\n";
      $OutBuf .= "function Schedule_Reply()\n";
      $OutBuf .= "  {\n";
      $OutBuf .= "  if ((Schedule.readyState==4) && (Schedule.status==200 || Schedule.status==400))\n";
      $OutBuf .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
      $OutBuf .= "  }\n";
      $OutBuf .= "</script>\n";

      $OutBuf .= "<form name='formy' method='post'>\n";
      $OutBuf .= "<div id='msgdiv'>\n";
      $OutBuf .= _("No data available.");
      $OutBuf .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
      $OutBuf .= "onClick=\"Schedule_Get('" . Traceback_uri() . "?mod=schedule_agent&upload=$uploadId&agent=agent_{$this->agentName}')\">\n";
      $OutBuf .= "</input>";
      $OutBuf .= "</div> \n";
      $OutBuf .= "</form>\n";

      $this->vars['pageContent'] = $OutBuf;
      return;
    }

    $AgentSelect = AgentSelect($this->agentName, $uploadId, $dataset, $agentId, "onchange=\"addArsGo('newds', 'copyright_dataset');\"");

    /** change the copyright  result when selecting one version of copyright */
    if (!empty($AgentSelect))
    {
      $action = Traceback_uri() . '?mod=' . GetParm('mod',PARM_RAW) . Traceback_parm_keep(array('upload','item'));

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

      $OutBuf .= "<form action=\"$action\" id=\"newds\" method=\"POST\">$AgentSelect</form>";
    }

    $selectKey = $filter == 'nolic' ? 'nolic' : 'all';
    $OutBuf .= "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$uploadId, $item);'>";
    foreach(array('all'=>_("Show all"), 'nolic'=> _("Show files without licenses")) as $key=>$text)
    {
      $selected = ($selectKey == $key) ? "selected" : "";
      $OutBuf .= "<option $selected value=\"$key\">$text</option>";
    }
    $OutBuf .= "</select>";

    $uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    list($tables, $tableVars) = $this->ShowUploadHist($uploadId, $item, $uri, $selectKey, $uploadtree_tablename, $agentId);
    $this->vars['tables'] = $tableVars;
    $this->vars['pageContent'] = $OutBuf . $tables;
    $this->vars['scriptBlock'] = $this->createScriptBlock();

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
    foreach ($Children as $c)
    {
      if (Iscontainer($c['ufile_mode']))
      {
        $ChildDirCount++;
      }
    }

    $VF .= "<table border=0>";
    foreach ($Children as $child)
    {
      if (empty($child))
      {
        continue;
      }
      $ChildCount++;
      
      global $Plugins;
      $ModLicView = &$Plugins[plugin_find_id($this->viewName)];
      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($child['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=".$this->viewName."&agent=$Agent_pk&upload=$upload_pk&item=$child[uploadtree_pk]";
      } else
      {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
      if (Iscontainer($child['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($child['uploadtree_pk'], $uploadtree_tablename);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      } else
      {
        $LicUri = NULL;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $LicCount = 0;

      $cellContent = Isdir($child['ufile_mode']) ? $child['ufile_name'].'/' : $child['ufile_name'];
      if (Iscontainer($child['ufile_mode']))
      {
        $cellContent = "<a href='$LicUri'><b>$cellContent</b></a>";
      }
      else if (!empty($LinkUri)) //  && ($LicCount > 0))
      {
        $cellContent = "<a href='$LinkUri'>$cellContent</a>";
      }
      $VF .= "<tr><td id='$child[uploadtree_pk]' align='left'>$cellContent</td><td>";

      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount, 0, "", ",") . "&nbsp;";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
        $ChildLicCount += $LicCount;
      }
      $VF .= "</td></tr>\n";
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
    $row =  $this->uploadDao->getUploadEntry($Uploadtree_pk, $this->uploadtree_tablename);
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

  abstract protected function createScriptBlock();

}
