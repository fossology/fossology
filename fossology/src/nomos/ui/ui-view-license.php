<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
/**
 * \file ui_view_license.php
 * 
 * \brief View License for nomos
 */

define("TITLE_ui_view_license", _("View License"));

class ui_view_license extends FO_Plugin
{
  var $Name       = "view-license";
  var $Title      = TITLE_ui_view_license;
  var $Version    = "1.0";
  var $Dependency = array("view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $NoMenu     = 0;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    /*    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
          $Item = GetParm("item",PARM_INTEGER);
          $Upload = GetParm("upload",PARM_INTEGER);
          if (!empty($Item) && !empty($Upload))
          {
          menu_insert("View::[BREAK]",-19);
          if (GetParm("mod",PARM_STRING) == $this->Name)
          {
          menu_insert("View::Nomos License",-21);
          menu_insert("View-Meta::Nomos License",-21);
          }
          else
          {
          menu_insert("View::Nomos License",-21,$URI,"View license histogram");
          menu_insert("View-Meta::Nomos License",-21,$URI,"View license histogram");
          }
          }*/
    $Lic = GetParm("lic",PARM_STRING);
    if (!empty($Lic)) { $this->NoMenu = 1; } 
  } // RegisterMenus()

  /**
   * \brief  Given a license path, insert
   * it into the View highlighting.
   */
  function ConvertLicPathToHighlighting($Row,$LicName,$RefURL=NULL)
  {
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];

    $First=1;
    if (!empty($Row['phrase_text']))
    {
      $LicName .= ": " . $Row['phrase_text'];
    }
    foreach(split(",",$Row['pfile_path']) as $Segment)
    {
      if (!empty($Segment))
      {
        $Parts = split("-",$Segment,2);
        if (empty($Parts[1])) { $Parts[1] = $Parts[0]; }
        if (empty($Row['lic_tokens'])) $Match = ""; /* No match for phrases */
        else $Match = (int)($Row['tok_match'] * 100 / ($Row['lic_tokens'])) . "%";
        if ($First) { $First = 0; $Color=-2; }
        else { $Color=-1; $LicName=NULL; }
        $View->AddHighlight($Parts[0],$Parts[1],$Color,$Match,$LicName,-1,$RefURL);
      }
    }
  } // ConvertLicPathToHighlighting()

  /**
   * \brief given a uploadtree_pk, lic_shortname
   * retrieve the license text and display it.
   */
  function ViewLicenseText($Item, $LicShortname, $TokPfileStart, $nomos_out)
  {
    global $PG_CONN;
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];    

    $sql = "select * from license_ref where rf_shortname = '$LicShortname' and rf_text != 'License by Nomos.';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    if (pg_num_rows($result) > 0)
    {
      while ($row = pg_fetch_assoc($result))
      {
        if (empty($row['rf_shortname'])) { continue; }

        $Text = "<div class='text'>";
        $Text .= "<H1>License: " . $row['rf_shortname'] . "</H1>\n";
        if (!empty($row['rf_fullname']))
        {
          $Text .= "<H2>License Fullname: " . $row['rf_fullname'] . "</H2>\n";
        }
        if (!empty($row['rf_url']) && (strtolower($row['rf_url']) != 'none'))
        {
          $Text .= "<b>Reference URL:</b> <a href=\"" . $row['rf_url'] . "\" target=_blank> " . $row['rf_url'] . "</a><br>\n";
        }
        if (!empty($row['rf_text']))
        {
          $Text .= "<b>License Text:</b>\n" . $row['rf_text'];
        }
        $Text .= "<hr>\n";
        $Text .= "</div>";
      }
    } else {
      $Text = "<div class='text'>"; 
      $Text .= "<H1>Original license text is not in the FOSSology database.</H1>\n";
      $Text .= "<hr>\n";
      $Text .= "</div>";
    }
    pg_free_result($result);
    //$Text .= $nomos_out;
    //$View->ShowView(NULL,"view",0,0,$Text);
    $Text = str_replace("\n","<br>\n",$Text);
    print($Text);    
  } // ViewLicenseText()

  /**
   * \brief Given an uploadtree_pk, return each
   * license record and canonical name.
   */
  function LicenseGetForFile(&$UploadtreePk)
  {
    global $PG_CONN;

    /* Get every real item */
    $SQL = "SELECT
      CASE
      WHEN lic_tokens IS NULL THEN licterm_name
      WHEN tok_match = lic_tokens THEN licterm_name
      ELSE '''' || licterm_name || '''-style'
      END AS licterm_name,
          agent_lic_meta.*,
          lic_tokens
            FROM uploadtree AS UT1,
          licterm_name, licterm, agent_lic_meta, agent_lic_raw
            WHERE
            uploadtree_pk = $UploadtreePk
            AND licterm_name.pfile_fk = UT1.pfile_fk
            AND licterm_pk=licterm_name.licterm_fk
            AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
            AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
            AND (lic_tokens IS NULL OR
                CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) > 0.5)
            AND licterm_name_confidence != 3
            ORDER BY agent_lic_meta_pk,licterm_name
            ;";
    $Results = pg_query($PG_CONN, $SQL);
    DBCheckResult($Results, $SQL, __FILE__, __LINE__);
    $Results_arr = pg_fetch_all($Results);

    /* Get every item found by term */
    $SQL = "SELECT
      licterm_name,
      agent_lic_meta.*,
      lic_tokens
        FROM uploadtree AS UT1,
      licterm_name, licterm, agent_lic_meta, agent_lic_raw
        WHERE
        uploadtree_pk = $UploadtreePk
        AND licterm_name.pfile_fk = UT1.pfile_fk
        AND licterm_pk=licterm_name.licterm_fk
        AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
        AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
        AND (lic_tokens IS NULL OR
            CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) > 0.5)
        AND licterm_name_confidence = 3
        ORDER BY agent_lic_meta_pk,licterm_name
        ;";
    $R2= pg_query($PG_CONN, $SQL);
    DBCheckResult($R2, $SQL, __FILE__, __LINE__);
    $R2_arr = pg_fetch_all($R2);

    /* Combine terms by name */
    for($i=0; !empty($R2_arr[$i]['licterm_name']); $i++)
    {
      if ($R2_arr[$i]['agent_lic_meta_pk'] == $R2_arr[$i+1]['agent_lic_meta_pk'])
      {
        $R2_arr[$i+1]['licterm_name'] = $R2_arr[$i]['licterm_name'] . ', ' . $R2_arr[$i+1]['licterm_name'];
      }
      else
      {
        $Results_arr[] = $R2_arr[$i];
      }
    }
    pg_free_result($Results);
    pg_free_result($R2);
    return($Results_arr);
  } // LicenseGetForFile()

  /**
   * This function is called when user output is
   * requested.  This function is responsible for content.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }


    $V="";
    global $Plugins;
    $View = &$Plugins[plugin_find_id("view")];
    $LicShortname = GetParm("lic",PARM_STRING);
    $LicIdSet = GetParm("licset",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $nomosagent_pk = GetParm("napk",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);

    /* only display nomos results if we know the nomosagent_pk 
       Otherwise, we don't know what results to display.  */
    $nomos_out = "";
    if (!empty($nomosagent_pk))
    { 
      $pfile_pk = 0;  // unknown, only have uploadtree_pk aka $Item
      $nomos_license_array = GetFileLicenses($nomosagent_pk, $pfile_pk, $Item);
      //$nomos_license_array = explode(",", $nomos_license_string);
      //print "nomos_license_string is:$nomos_license_string\n";
      //print_r($nomos_license_array);

      if (!empty($nomos_license_array)) 
      {
        $text = _("The Nomos license scanner found:");
        $nomos_out = "$text <b>";
      }
      $rec_flag = 0;
      foreach($nomos_license_array as $one_license_pk => $one_license) {
        $one_license = trim($one_license);
        if (0 == $rec_flag) {
          $rec_flag = 1;
        } else {
          $nomos_out .= " ,";
        } 
        $nomos_out .= "<b>";
        $nomos_out .= "<a title='Show License Reference.' href='javascript:;' onClick=\"javascript:window.open('";
        $nomos_out .= Traceback_uri();
        $nomos_out .= "?mod=view-license";
        $nomos_out .= "&lic=";
        $nomos_out .= $one_license;
        $nomos_out .= "&upload=";
        $nomos_out .= $Upload;
        $nomos_out .= "&item=";
        $nomos_out .= $Item;
        $nomos_out .= "','License Text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$one_license</a>";
        $nomos_out .= "</b>";
      }
    }

    if (!empty($LicShortname))
    {
      $this->ViewLicenseText($Item,$LicShortname,$LicIdSet, $nomos_out);
      return;
    }

    if (empty($Item)) { return; }
    $ModBack = GetParm("modback",PARM_STRING);
    if (empty($ModBack) && (!empty($nomos_out)))  $ModBack = "nomoslicense";

    /* Load bSAM licenses for this file */
    $Results = $this->LicenseGetForFile($Item);

    /* Show bSAM licenses  */
    if (count($Results) <= 0)
    {
      /*
         Since LicenseGetForFile() doesn't distinguish between files that
         bSAM ran on and found no licenses, and files that bSAM was never
         run on (both cases return no $Results rows), don't tell the
         user a misleading "No licenses found".
       */
      // $View->AddHighlight(-1,-1,'white',NULL,"No licenses found");
      if (empty($ModBack)) $ModBack = "browse";
    }
    else
    {
      foreach($Results as $R)
      {
        if (empty($R['pfile_path'])) { continue; }
        if (!empty($R['phrase_text']))
        {
          $RefURL = NULL;
          if ($R['licterm_name'] != 'Phrase') { $R['phrase_text'] = ''; }
        }
        else
        {
          $RefURL=Traceback() . "&lic=" . $R['lic_fk'] . "&licset=" . $R['tok_pfile_start'];
        }
        $this->ConvertLicPathToHighlighting($R,$R['licterm_name'],$RefURL);
      }
      if (empty($ModBack)) $ModBack = "license";
    }

    $View->ShowView(NULL,$ModBack, 1, 1, $nomos_out);
    return;
  } // Output()

};
$NewPlugin = new ui_view_license;
$NewPlugin->Initialize();
?>
