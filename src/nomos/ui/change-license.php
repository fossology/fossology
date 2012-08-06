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

/**
 * \file change-license.php
 * \brief change license of one file
 * \note if one file has multiple liceneses, you only can change one each time, if you want to delete this 
 * lecense, you can change it to No_license_found
 */

define("TITLE_change_license", _("Change License"));

class change_license extends FO_Plugin {

  public $Name = "change_license";
  public $Title = TITLE_change_license;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_ANALYZE;

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
   * \brief display license audit trail on the pop up window
   *
   * \param $LicenseFileId - file license ID (fl_pk in table license_file)
   * \param $Upload - upload id
   * \param $Item - uploadtree id
   *
   * \return audit trail html
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

    /**　query license_file_audit, license_file_audit record the origial license */
    $sql = "SELECT rf_fk, user_fk, date, reason from license_file_audit where fl_fk = $LicenseFileId order by date DESC;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $org_lic = "";
    $obj_lic = "";
    $user = "";
    $V = "";
    $V .= "<table border='1'>\n";
    $text = _("License");
    $text1 = _("Changed To");
    $text2 = _("Reason");
    $text3 = _("By");
    $text4 = _("Date");
    $V .= "<tr><th>$text</th><th>$text1</th><th>$text4</th><th>$text3</th><th>$text2</th></tr>\n";
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
      $V .= "<td>$row[date]</td>";
      $V .= "<td>$user</td>";
      $V .= "<td>$row[reason]</td>";
      $V .= "</tr>";
      $obj_lic = $row1['rf_shortname'];
    }
    pg_free_result($result);
    $V .= "</table><br>\n";

    return ($V);
  } // ViewLicenseAuditTrail()

  /** 
   * \brief change the license reference 
   * 
   * \param $OriginalLicense - original license 
   * \param $ObjectiveLicense - objective license
   * \param $Reason - why do this change
   * \param $FileName - file name 
   *
   * \return NULL
   */
  function Change(&$OriginalLicense, &$ObjectiveLicense, &$Reason, &$FileName)
  {
    global $SysConf;
    global $PG_CONN;
    $fl_pk = GetParm("fl_pk",PARM_STRING);

    /** get original license reference short name */
    if (!empty($fl_pk))
    {
      $sql = "select rf_shortname from license_file_ref where fl_pk = $fl_pk;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      $OriginalLicense = $row['rf_shortname'];
      pg_free_result($result);
    } else return NULL;

    /** change the license */
    if (!empty($fl_pk) && !empty($ObjectiveLicense) && empty($DeleteFlag)) {
      $sql = "select rf_pk from license_ref where rf_shortname = '$ObjectiveLicense';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);

      $count = pg_num_rows($result);
      if (0 == $count) { // the objective license does not exist in FOSSology
        pg_free_result($result);
        $text = _("Error: license ");
        $text1 =_("does not exist in FOSSology.");
        $Msg = "$text '$ObjectiveLicense' $text1";
        print displayMessage($Msg,$keep);
        return (NULL);
      }
      $row = pg_fetch_assoc($result);
      $rf_fk = $row['rf_pk'];
      pg_free_result($result);

      if ($ObjectiveLicense === $OriginalLicense) { // original license is same with objective license
        $text = _("Fatal: Objective license");
        $text1 = _("is same to original license");
        $Msg = "$text '$OriginalLicense' $text1 '$ObjectiveLicense'.";
        print displayMessage($Msg,"");
        return (NULL);
      } else if (!empty($ObjectiveLicense)) { // complete change
        $text = _("is changed to");
        $Msg = "'$OriginalLicense' $text '$ObjectiveLicense'.";
        print displayMessage($Msg,"");
      }

      $user_pk = $SysConf['auth']['UserId'];
      /** get original license reference ID */
      $sql = "select rf_pk from license_ref where rf_shortname = '$OriginalLicense';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      $org_rf_fk = $row['rf_pk'];
      pg_free_result($result);

      $Reason = pg_escape_string($Reason); // perhaps there are special characters in reason field 

      /** save the changed license */
      $sql = "INSERT INTO license_file_audit (fl_fk, rf_fk, user_fk, reason) VALUES ($fl_pk, $org_rf_fk, $user_pk, '$Reason');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);

      /** update license_file table */
      $sql = "UPDATE license_file SET rf_fk = $rf_fk, rf_timestamp=now() WHERE fl_pk = $fl_pk;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
      return NULL;
    }
  } // Change()

  /**
   * \brief get all license list in fossology
   *
   * \return license list options
   */
  function LicenseList()
  {
    global $PG_CONN;
    $sql = "SELECT rf_shortname from license_ref order by rf_shortname asc;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $LicenseList = "";
    while($row = pg_fetch_assoc($result))
    {
      $LicenseList .= "<option value = '$row[rf_shortname]'>$row[rf_shortname]</option>";
    }
    pg_free_result($result);
    return $LicenseList;
  }

  /** 
   * \brief display the license changing page
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $ObjectiveLicense = GetParm("object_license",PARM_TEXT);
    $ObjectiveLicense = trim($ObjectiveLicense);
    $Reason = GetParm("change_reason",PARM_TEXT);
    $Reason = trim($Reason);
    $Agent_pk = GetParm("napk",PARM_STRING);
    $upload_fk = GetParm("upload",PARM_STRING);
    $uploadtree_pk = GetParm("item",PARM_STRING);
    $fl_pk = GetParm("fl_pk",PARM_STRING);
    $OriginalLicense = "";
    $FileName = "";

    $V="";
    /* Get uploadtree table name */
    $uploadtree_tablename = GetUploadtreeTablename($upload_fk);

    $V .= Dir2Browse('browse', $uploadtree_pk, NULL, 1,"View", -1, '', '', $uploadtree_tablename) . "<P />\n";

    $this->Change($OriginalLicense, $ObjectiveLicense, $Reason, $FileName);

    if ($this->IsChanged($fl_pk)) // if this license has been change, display the change trail 
      $V .= $this->ViewLicenseAuditTrail($fl_pk, $upload_fk, $uploadtree_pk);

    $V.= "<form enctype='multipart/form-data' method='post'>\n";
    $V .= "<table border='1'>\n";
    $text = _("License");
    $text1 = _("Changed To");
    $text2 = _("Reason");
    $V .= "<tr><th width='20%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";
    $V .= "<tr>\n";
    $V .= "<td>$OriginalLicense</td>\n";
    // $V .= "<td> <input type='text' style='width:100%' name='object_license'></td>\n";
    $V .= "<td> <select name='object_license'>\n";
    $V .= $this->LicenseList();
    $V .= "</select></td>";
    $V .= "<td> <input type='text' style='width:100%' name='change_reason'></td>\n";
    $V .= "</tr>\n";
    $V .= "</table><br>\n";

    $V .= "<input type='submit' value='Submit'>";
    $V.= "</form>\n";
    print $V;
  }
}

$NewPlugin = new change_license;
?>
