<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

define("TITLE_FOCONFIG", _("Configuration Variables"));

/**
 * \class foconfig extend from FO_Plugin
 * \brief display and set FOSSology configuration
 */
class foconfig extends FO_Plugin
{
  var $CreateAttempts = 0;
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name       = "foconfig";
    $this->Title      = TITLE_FOCONFIG;
    $this->MenuList   = "Admin::Customize";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    $this->PluginLevel = 50;    // run before 'regular' plugins
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Generate HTML output.
   */
  function HTMLout()
  {
    global $PG_CONN;
    $OutBuf="";

    /* get config rows from sysconfig table */
    $sql = "select * from sysconfig order by group_name, group_order";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $Group = "";
    $InputStyle = "style='background-color:#dbf0f7'";
    $OutBuf .= '<style> table.myTable > tbody > tr:first-child > td:first-child{width:20%} </style>';
    $OutBuf .= "<form method='POST'>";
    while ($row = pg_fetch_assoc($result)) {
      if ($Group != $row['group_name']) {
        if ($Group) {
          $OutBuf .= "</table><br>";
        }
        $Group = $row['group_name'];
        $OutBuf .= '<table border=1 class="myTable table table-striped" style="border-collapse: unset;">';
      }

      $OutBuf .= "<tr><td>$row[ui_label]</td><td>";
      switch ($row['vartype']) {
        case CONFIG_TYPE_INT:
        case CONFIG_TYPE_TEXT:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<INPUT type='text' name='new[$row[variablename]]' size='70' value='$ConfVal' title='$row[description]' $InputStyle>";
          $OutBuf .= "<br>$row[description]";
          break;
        case CONFIG_TYPE_TEXTAREA:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<br><textarea name='new[$row[variablename]]' rows=3 cols=80 title='$row[description]' $InputStyle>$ConfVal</textarea>";
          $OutBuf .= "<br>$row[description]";
          break;
        case CONFIG_TYPE_PASSWORD:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<INPUT type='password' name='new[$row[variablename]]' size='70' value='$ConfVal' title='$row[description]' $InputStyle>";
          $OutBuf .= "<br>$row[description]";
          break;
        case CONFIG_TYPE_DROP:
          $ConfVal = htmlentities($row['conf_value']);
          $Options = explode("|",$row['option_value']);
          $OutBuf .= "<select name='new[$row[variablename]]' title='$row[description]' $InputStyle>";
          foreach ($Options as $Option) {
            $matches = array();
            preg_match('/([ \\w]+)[{​​​​](.*)[}​​​​]/', $Option, $matches);
            $Option_display = $matches[1];
            $Option_value = $matches[2];
            $OutBuf .= "<option $InputStyle value='$Option_value' ";
            if ($ConfVal == $Option_value) {
              $OutBuf .= "selected";
            }
            $OutBuf .= ">$Option_display</option>";
          }
          $OutBuf .= "</select>";
          $OutBuf .= "<br>$row[description]";
          break;
        case CONFIG_TYPE_BOOL:
          $ConfVal = filter_var($row['conf_value'], FILTER_VALIDATE_BOOLEAN);
          $checked = $ConfVal ? "checked" : "";
          $ConfVal = $ConfVal ? "true" : "false";
          $OutBuf .= "<input type='checkbox' name='new[" . $row['variablename'] .
            "]' id='" . $row['variablename'] . "' value='true' title='" .
            $row['description'] . "' $InputStyle $checked />";
          $OutBuf .= "<label for='" . $row['variablename'] .
            "'>" . $row['description'] . "</label>";
          break;
        default:
          $OutBuf .= "Invalid configuration variable. Unknown type.";
      }
      $OutBuf .= "</td></tr>";
      $OutBuf .= "<INPUT type='hidden' name='old[$row[variablename]]' value='$ConfVal'>";
    }
    $OutBuf .= "</table>";
    pg_free_result($result);

    $btnlabel = _("Update");
    $OutBuf .= "<p><input type='submit' value='$btnlabel'>";
    $OutBuf .= "</form>";

    return $OutBuf;
  }

  /**
   * \brief Generate output.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $newarray = GetParm("new", PARM_RAW);
    $oldarray = GetParm("old", PARM_RAW);

    if (!empty($newarray)) {
      // Get missing keys from new array (unchecked checkboxes are not sent)
      $boolFalseArray = array_diff_key($oldarray, $newarray);
      foreach ($boolFalseArray as $varname => $value) {
        // Make sure it was boolean data
        $isBoolean = $this->dbManager->getSingleRow("SELECT 1 FROM sysconfig " .
          "WHERE variablename = $1 AND vartype = " . CONFIG_TYPE_BOOL,
          array($varname), __METHOD__ . '.checkIfBool');
        if (! empty($isBoolean)) {
          $newarray[$varname] = 'false';
        }
      }
    }

    /* Compare new and old array
     * and update DB with new values */
    $UpdateMsg = "";
    $ErrorMsg="";
    if (! empty($newarray)) {
      // Fetch endpoints from OIDC documentation
      if (! empty($newarray["OidcDiscoveryURL"]) &&
          $newarray["OidcDiscoveryURL"] != $oldarray["OidcDiscoveryURL"]) {
        $this->updateOidcEndpoints($newarray, $oldarray);
      }
      foreach ($newarray as $VarName => $VarValue) {
        if ($VarValue != $oldarray[$VarName]) {
          /* get validation_function row from sysconfig table */
          $sys_array = $this->dbManager->getSingleRow("select validation_function, ui_label from sysconfig where variablename=$1",array($VarName),__METHOD__.'.getVarNameData');
          $validation_function = $sys_array['validation_function'];
          $ui_label = $sys_array['ui_label'];
          $is_empty = empty($validation_function);
          /* 1. the validation_function is empty
           2. the validation_function is not empty, and after checking, the value is valid
          update sysconfig table
          */
          if ($is_empty || (! $is_empty && (1 == $validation_function($VarValue)))) {
            $this->dbManager->getSingleRow(
              "update sysconfig set conf_value=$1 where variablename=$2",
              array($VarValue, $VarName), __METHOD__ . '.setVarNameData');
            if (! empty($UpdateMsg)) {
              $UpdateMsg .= ", ";
            }
            $UpdateMsg .= $VarName;
          } else if (! $is_empty && (0 == $validation_function($VarValue))) {
            /*
             * the validation_function is not empty, but after checking, the value
             * is invalid
             */
            if (! strcmp($validation_function, 'check_boolean')) {
              $warning_msg = _(
                "Error: You set $ui_label to ".htmlspecialchars($VarValue).". Valid  values are 'true' and 'false'.");
              echo "<script>alert('$warning_msg');</script>";
            } else if (strpos($validation_function, "url")) {
              $warning_msg = _(
                "Error: $ui_label ".htmlspecialchars($VarValue).", is not a reachable URL.");
              echo "<script>alert('$warning_msg');</script>";
            }

            if (! empty($ErrorMsg)) {
              $ErrorMsg .= ", ";
            }
            $ErrorMsg .= $VarName;

          }
        }
      }
      if (! empty($UpdateMsg)) {
        $UpdateMsg .= _(" updated.");
      }
      if (! empty($ErrorMsg)) {
        $ErrorMsg .= _(" Error occurred.");
      }
    }

    $OutBuf = '';
    if ($this->OutputType == 'HTML') {
      $OutBuf .= "<div>";
      if ($UpdateMsg) {
        $OutBuf .= "<span style='background-color:#99FF99'>$UpdateMsg</style>";
      }
      if ($ErrorMsg) {
        $OutBuf .= "<span style='background-color:#FF8181'>$ErrorMsg</style><hr>";
      }
      $OutBuf .= "</div> <hr>";
      $OutBuf .= $this->HTMLout();
    }
    $this->vars['content'] = $OutBuf;
  }

  /**
   * @brief Update OIDC endpoints from OIDC discovery document
   *
   * Fetch the OIDC discovery document and replace the old values of required
   * endpoints. Update the values in the newarray.
   * The function simply returns in case of error.
   *
   * @param[in,out] array $newarray Array with new values
   * @param[in,out] array $oldarray Array with old values
   */
  public function updateOidcEndpoints(&$newarray, &$oldarray)
  {
    global $SysConf;
    $client = new Client();
    $proxy = [];
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy['http'] = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy['https'] = $SysConf['FOSSOLOGY']['https_proxy'];
    }
    if (array_key_exists('no_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['no_proxy'])) {
      $proxy['no'] = explode(',', $SysConf['FOSSOLOGY']['no_proxy']);
    }
    try {
      $res = $client->request("GET", $newarray["OidcDiscoveryURL"], [
        "proxy" => $proxy
      ]);
    } catch (RequestException $e) {
      return;
    }
    if ($res->getStatusCode() !== 200) {
      return;
    }
    $body = (string)$res->getBody();
    $body = json_decode($body, true);
    // Reset old values to make sure the update happens
    $oldarray["OidcIssuer"] = "";
    $oldarray["OidcAuthorizeURL"] = "";
    $oldarray["OidcAccessTokenURL"] = "";
    $oldarray["OidcResourceURL"] = "";
    $oldarray["OidcJwksURL"] = "";
    $newarray["OidcIssuer"] = $body["issuer"];
    $newarray["OidcAuthorizeURL"] = $body["authorization_endpoint"];
    $newarray["OidcAccessTokenURL"] = $body["token_endpoint"];
    $newarray["OidcResourceURL"] = $body["userinfo_endpoint"];
    $newarray["OidcJwksURL"] = $body["jwks_uri"];
  }
}

$NewPlugin = new foconfig;
$NewPlugin->Initialize();