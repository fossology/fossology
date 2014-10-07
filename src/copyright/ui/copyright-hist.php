<?php
/***********************************************************
 * Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
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

define("TITLE_copyrightHistogram", _("Copyright/Email/URL Browser NEW"));

class CopyrightHistogram  extends FO_Plugin {

  /**
   * @var string
   */
  private $uploadtree_tablename;

  /**
   * @var CopyrightDao
   */

  private  $copyrightDao;

  function __construct()
  {
    $this->Name = "copyright-hist";
    $this->Title = TITLE_copyrightHistogram;
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






private function getTableForSingleType($type,$description,$descriptionUnique,$descriptionTotal, $upload_pk, $uploadtreeId, $filter, $uploadtree_tablename, $Agent_pk){

  $output = "<div><table border=1 width='100%' id='copyright".$type."'>\n";
  $output .= "</table></div><br/>\n";

      $tableColumns = '[
      { "sTitle" : "'._("Count").'", "sClass": "right read_only" ,"sWidth" : "5%" },
      { "sTitle" : "'.$description.'", "sClass": "left"},
      { "sTitle" : "", "sClass" : "center read_only", "sWidth" : "10%", "bSortable" : false }
    ]';
    $tableSorting = json_encode($this->returnSortOrder());

      $tableLanguage = '[
      {"sInfo" : "Showing _START_ to _END_ of _TOTAL_ '.$descriptionTotal.'"},
      {"sSearch": "Search _INPUT_ " },
         { "sInfoPostFix" : "" },
         {"sLengthMenu" : "Display <select><option value=\"10\">10</option><option value=\"25\">25</option><option value=\"50\">50</option><option value=\"100\">100</option></select> files" }
      ]';
//      "oLanguage": '.$tableLanguage.',

      $dataTableConfig =
        '{  "bServerSide": true,
            "sAjaxSource": "?mod=ajax-copyright-hist&action=getData",
            "fnServerData": function ( sSource, aoData, fnCallback ) {
              aoData.push( { "name":"upload" , "value" : "'.$upload_pk.'" } );
              aoData.push( { "name":"item" , "value" : "'.$uploadtreeId.'" } );
              aoData.push( { "name":"agent" , "value" : "'.$Agent_pk.'" } );
              aoData.push( { "name":"type" , "value" : "'.$type.'" } );
              aoData.push( { "name":"filter" , "value" : "'.$filter.'" } );
              $.getJSON( sSource, aoData, fnCallback ).fail( function() {
              if (confirm("You are not logged in. Go to login page?"))
                window.location.href="'.  Traceback_uri(). '?mod=auth";
            });
          },
      "aoColumns": '.$tableColumns.',
      "aaSorting": '.$tableSorting.',
      "iDisplayLength": 50,
      "bProcessing": true,
      "bStateSave": true,
      "sCookiePrefix" : "fossology_",
      "bRetrieve": true
    }';

  $editableConfiguration  = '{
    "sReadOnlyCellClass": "read_only",
    "sSelectedRowClass" : "selectedRow",
    "sUpdateURL": "?mod=ajax-copyright-hist&action=update&type='.$type.'" ,
    "fnOnEditing" : function(input) {
                      var value = input[0].value;
                      var isValid = (value) && !(/^\s*$/.test(value));
                      if (isValid) {
                         var id = input.parents("tr:first")[0].id;
                         var hash = id.split(",")[2];
                         $("#delete'.$type.'" + hash).hide();
                         var updateElement = $("#update'.$type.'" + hash);
                         updateElement.text("updating...");
                         updateElement.show();
                      }
                      return isValid;
                    },
    "sSuccessResponse" : "success",
    }';





    $output   .= "<script>
              function createTable".$type."() {
                    var otable = $('#copyright".$type."').dataTable(". $dataTableConfig . ").makeEditable($editableConfiguration);
                    // var settings = otable.fnSettings(); // for debugging
                    return otable;
                };

              function delete".$type."(upload,item,hash) {
                 $.ajax({
                   type: 'POST',
                   dataType: 'text',
                   url: '?mod=ajax-copyright-hist&action=delete&type=$type',
                   data: { id : upload + ',' + item + ',' + hash },
                   success: function(data) { $('#copyright$type').dataTable().fnDraw(false); },
                   error: function() { alert('error'); }
                 });
              }
            </script>";


  return $output;

}


  /**
   * @param $Uploadtree_pk
   * @param $filter
   * @param $Agent_pk
   * @param $ordercount
   * @param $ordercopyright
   * @param $rows
   * @return array
   */
  private function getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk)
  {

    $type = 'statement';
    $decription = _("Copyright");
    $descriptionUnique = _("Unique Copyrights");
    $descriptionTotal = _("Total Copyrights");


    $VCopyright  =  $this->getTableForSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);

    $type = 'email';
    $decription = _("Email");
    $descriptionUnique = _("Unique Emails");
    $descriptionTotal = _("Total Emails");

    $VEmail =  $this->getTableForSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);


    $type = 'url';
    $decription = _("URL");
    $descriptionUnique = _("Unique URLs");
    $descriptionTotal = _("Total URLs");

    $VUrl=  $this->getTableForSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);

    return array( $VCopyright, $VEmail, $VUrl);
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
  function ShowUploadHist($upload_pk, $Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
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
      $ModLicView = &$Plugins[plugin_find_id("copyright-view")];
      return($ModLicView->Output() );
    }

    list( $VCopyright, $VEmail, $VUrl) = $this->getTableContent($upload_pk,$Uploadtree_pk, $filter, $Agent_pk);

    /* Combine VF and VLic */
    $text = _("Jump to");
    $text1 = _("Emails");
    $text2 = _("Copyright Statements");
    $text3 = _("URLs");
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>$text: <a href=\"#emails\">$text1</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>Jump to: <a href=\"#statements\">$text2</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>Jump To: <a href=\"#statements\">$text2</a> | <a href=\"#emails\">$text1</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

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
        $Agent_name = "copyright";
        $dataset = "copyright_dataset";
        $arstable = "copyright_ars";
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
          $AgentSelect = AgentSelect($Agent_name, $Upload, true, $dataset, $dataset, $Agent_pk,
              "onchange=\"addArsGo('newds', 'copyright_dataset');\"");

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

          $OutBuf .= $this->ShowUploadHist($Upload, $Item, $Uri, $filter, $uploadtree_tablename, $Agent_pk);
        }
      }
      $OutBuf .= "</font>\n";

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
  private function getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk)
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
      $ModLicView = &$Plugins[plugin_find_id("copyright-view")];
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
  private function isADirectory($Uploadtree_pk)
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


  static public function returnSortOrder () {
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

  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::Copyright/Email/URL",1);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View copyright/email/url histogram");
        menu_insert("Browse::Copyright/Email/URL",10,$URI,$text);
      }
    }
  } // RegisterMenus()

};

$NewPlugin = new CopyrightHistogram;
//$NewPlugin->Initialize();