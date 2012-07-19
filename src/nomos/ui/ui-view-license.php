<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
    foreach(explode(",",$Row['pfile_path']) as $Segment)
    {
      if (!empty($Segment))
      {
        $Parts = explode("-",$Segment,2);
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
   * \brief display license audit trail on the pop up window
   *
   * \param $LicenseFileId - file license ID (fl_pk in table license_file)
   * \param $Upload - upload id
   * \param $Item - uploadtree id
   */
  function ViewLicenseAuditTrail($LicenseFileId, $Upload, $Item)
  {
    global $PG_CONN;

    $FileName = "";

    /** get file name */
    $uploadtree_tablename = GetUploadtreeTableName($Upload);
    $sql = "SELECT ufile_name from $uploadtree_tablename where uploadtree_pk = $Item;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $FileName = $row['ufile_name'];
    pg_free_result($result);

    /** look like: Audit trail on file 'LargeLICENSE.txt' */
    $text = _("Audit trail on file");
    print "<b> $text '$FileName' </b> <br><hr>";
    
    /**　query license_file_audit, license_file_audit record the origial license */
    $sql = "SELECT rf_fk, user_fk, date, reason from license_file_audit where fl_fk = $LicenseFileId order by date DESC;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $org_lic = "";
    $obj_lic = "";
    $user = "";
    $V .= "<table border='1'>\n";
    $text = _("Original License");
    $text1 = _("Objective License");
    $text2 = _("Reason");
    $text3 = _("User");
    $text4 = _("Date");
    $V .= "<tr><th>$text</th><th>$text1</th><th>$text2</th><th>$text3</th><th>$text4</th></tr>\n";
    $changed_times = pg_num_rows($result);
    /** get latest license, rf_shortname in license_file alway is latest license  */
    $sql = "SELECT rf_shortname from license_file_ref where fl_pk = $LicenseFileId;";
    $result1 = pg_query($PG_CONN, $sql);
    DBCheckResult($result1, $sql, __FILE__, __LINE__);
    $row1 = pg_fetch_assoc($result1);
    $obj_lic = $row1['rf_shortname']; // get the lastest license from license_file_ref
    pg_free_result($result1);
    while ($row = pg_fetch_assoc($result))
    {
      $user_id = $row['user_fk'];
      $sql = "select user_name from users where user_pk = $user_id;";
      $result1 = pg_query($PG_CONN, $sql);
      DBCheckResult($result1, $sql, __FILE__, __LINE__);
      $row1 = pg_fetch_assoc($result1);
      $user = $row1['user_name'];
      pg_free_result($result1);

      $sql = "SELECT rf_shortname from license_ref where rf_pk = $row[rf_fk];";
      $result1 = pg_query($PG_CONN, $sql);
      DBCheckResult($result1, $sql, __FILE__, __LINE__);
      $row1 = pg_fetch_assoc($result1);
      $org_lic = $row1['rf_shortname'];
      pg_free_result($result1);
      $V .= "<tr>";
      $V .= "<td>$org_lic</td>";
      $V .= "<td>$obj_lic</td>";
      $V .= "<td>$row[reason]</td>";
      $V .= "<td>$user</td>";
      $V .= "<td>$row[date]</td>";
      $V .= "</tr>";
      $obj_lic = $row1['rf_shortname'];
    }
    pg_free_result($result);
    $V .= "</table><br>\n";

    print($V);    
  } // ViewLicenseAuditTrail()

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
   * \brief check if this file license has been changed
   *
   * \param $fl_pk - file license id
   *
   * \return 1: yes, changed, 0: no not changed
   */
  function IsChanged($fl_pk) 
  {
    global $PG_CONN;
    $sql = "select count(*) from license_file_audit where fl_fk = $fl_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if ($row['count'] == 0) return 0;
    else return 1;
  } // IsChanged()

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
    $LicenseFileId = GetParm("fl_pk",PARM_INTEGER);
    
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
      foreach($nomos_license_array as $fl_pk => $one_license) {
        $one_license = trim($one_license);
        if (0 == $rec_flag) {
          $rec_flag = 1;
        } else {
          $nomos_out .= " ,";
        } 
        
        /** pop up license audit trail page*/
        $change_license = "";
        if ($this->IsChanged($fl_pk)) {
          $change_license .= "onmouseout=\"javascript:window.open('";
          $change_license .= Traceback_uri();
          $change_license .= "?mod=view-license";
          $change_license .= "&fl_pk=";
          $change_license .= $fl_pk;
          $change_license .= "&lic=";
          $change_license .= $one_license;
          $change_license .= "&upload=";
          $change_license .= $Upload;
          $change_license .= "&item=";
          $change_license .= $Item;
          $text = _("License Reference Trail");
          $change_license .= "','$text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\"";
        }

        $text = _("A mouse click: Show License Reference; Mouse out: Show License Audit Trail.");
        $nomos_out .= "<a title='$text' href='javascript:;'";
        $nomos_out .= $change_license;
        $nomos_out .= "onClick=\"javascript:window.open('";
        $nomos_out .= Traceback_uri();
        $nomos_out .= "?mod=view-license";
        $nomos_out .= "&lic=";
        $nomos_out .= $one_license;
        $nomos_out .= "&upload=";
        $nomos_out .= $Upload;
        $nomos_out .= "&item=";
        $nomos_out .= $Item;
        $text = _("License Text");
        $nomos_out .= "','$text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\"";
        $nomos_out .= ">$one_license";
        $nomos_out .= "</a>";
        /** edit this license */
        $text = _("Edit");
        /** go to the license change page */
        if (plugin_find_id('change_license') >= 0) {
          $text1 = _("Edit This Licence Reference");
          $nomos_out .= "<a title='$text1' href='" . Traceback_uri() . "?mod=change_license&fl_pk=$fl_pk' style='color:#ff0000;font-style:italic'>[$text]</a>";
        }
      }
    }

    if (!empty($LicenseFileId))
    {
      $this->ViewLicenseAuditTrail($LicenseFileId, $Upload, $Item); // keeping an audit trail of who changed what why and when
      return;
    }

    if (!empty($LicShortname)) // dispaly the detailed license text of one license
    {
      $this->ViewLicenseText($Item,$LicShortname,$LicIdSet, $nomos_out);
      return;
    }

    if (empty($Item)) { return; }
    $ModBack = GetParm("modback",PARM_STRING);
    if (empty($ModBack) && (!empty($nomos_out)))  $ModBack = "nomoslicense";

    /* Load bSAM licenses for this file */
    $bsam_plugin_key = plugin_find_id("license"); /** -1, can not find bsam plugin, or find */
    /** if the bsam plugin does exist, get and show bSAM licenses */
    if (-1 != $bsam_plugin_key)
    {
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
    }

    $View->ShowView(NULL,$ModBack, 1, 1, $nomos_out);
    return;
  } // Output()

};
$NewPlugin = new ui_view_license;
$NewPlugin->Initialize();
?>
