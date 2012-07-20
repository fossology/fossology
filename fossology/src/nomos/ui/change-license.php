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
        print displayMessage($Msg,$keep);
        return (NULL);
      } else if (!empty($ObjectiveLicense)) { // complete change
        $text = _("is changed to");
        $Msg = "'$OriginalLicense' $text '$ObjectiveLicense'.";
        print displayMessage($Msg,$keep);
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
   * \brief display the license changing page
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $ObjectiveLicense = GetParm("object_license",PARM_TEXT);
    $ObjectiveLicense = trim($ObjectiveLicense);
    $Reason = GetParm("change_reason",PARM_TEXT);
    $Reason = trim($Reason);
    $OriginalLicense = "";
    $FileName = "";

    $this->Change($OriginalLicense, $ObjectiveLicense, $Reason, $FileName);

    $V="";
    $V.= "<form enctype='multipart/form-data' method='post'>\n";
    $V .= "<table border='1'>\n";
    $text = _("Original License");
    $text1 = _("Objective License");
    $text2 = _("Reason");
    $V .= "<tr><th width='20%'>$text</th><th width='20%'>$text1</th><th>$text2</th></tr>\n";
    $V .= "<tr>\n";
    $V .= "<td>$OriginalLicense</td>\n";
    $V .= "<td> <input type='text' style='width:100%' name='object_license'></td>\n";
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
