<?php
/*
 SPDX-FileCopyrightText: © Darshan Kansagara <kansagara.darshan97@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
 Author: Darshan Kansagara <kansagara.darshan97@gmail.com>
*/

use Fossology\Lib\Db\DbManager;

define("TITLE_FOSSDASH_CONFIG", _("Fossdash Configuration"));

/**
 * \class FossdashConfig extend from FO_Plugin
 * \brief display and set FOSSology configuration
 */
class FossdashConfig extends FO_Plugin
{
  var $CreateAttempts = 0;
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name       = "FossdashConfig";
    $this->Title      = TITLE_FOSSDASH_CONFIG;
    $this->MenuList   = "Admin::Fossdash";
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

    /* get config rows from fossdashconfig table */
    $sql = "select * from fossdashconfig order by group_name, group_order";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $Group = "";
    $InputStyle = "style='background-color:#dbf0f7'";
    $OutBuf .= '<style> table.myTable > tbody > tr:first-child > td:first-child{width:20%} </style>';
    $OutBuf .= "<form method='POST' enctype='multipart/form-data'>";
    while ($row = pg_fetch_assoc($result)) {
      if ($Group != $row['group_name']) {
        if ($Group) {
          $OutBuf .= '</table><br>';
        }
        $Group = $row['group_name'];
        $OutBuf .= '<table border=1 class="myTable table table-striped" style="border-collapse: unset;" >';
      }
      if ($row['variablename']=="InfluxDBUser" || $row['variablename']=="InfluxDBUserPassword") {
        $OutBuf .= "<tr id='rowId$row[variablename]' style='display: none;'><td>$row[ui_label]</td><td>";
      } else {
        $OutBuf .= "<tr id='rowId$row[variablename]'><td>$row[ui_label]</td><td>";
      }

      switch ($row['vartype']) {
        case CONFIG_TYPE_INT:
        case CONFIG_TYPE_TEXT:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<INPUT type='text' name='new[$row[variablename]]' size='70' value='$ConfVal' title='$row[description]' $InputStyle>";
          $OutBuf .= "<br>$row[description]";
          break;
        case CONFIG_TYPE_TEXTAREA:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<br><textarea name='new[$row[variablename]]' rows=3 cols=100 title='$row[description]' $InputStyle>$ConfVal</textarea>";
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
            preg_match('/(\\w+)[{](.*)[}]/', $Option, $matches);
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
        default:
          $OutBuf .= "Invalid configuration variable.  Unknown type.";
      }
      $OutBuf .= "</td></tr>";
      $OutBuf .= "<INPUT type='hidden' name='old[$row[variablename]]' value='$ConfVal'>";
    }
    $OutBuf .= "</table>";
    pg_free_result($result);

    $btnlabel = _("Update");
    $OutBuf .= "<p><input type='submit' class='btn btn-secondary btn-sm' style='display: block; margin: 0 auto;' value='$btnlabel'>";
    $OutBuf .= "</form>";

    $scriptToHideShow = '
    <script>
      function showHide() {
          if($(\'[name="new[AuthType]"]\').val() == "0") {
              $("#rowIdInfluxDBToken").show();
              $("#rowIdInfluxDBUser").hide();
              $("#rowIdInfluxDBUserPassword").hide();
              
          } else {
              $("#rowIdInfluxDBToken").hide();
              $("#rowIdInfluxDBUser").show();
              $("#rowIdInfluxDBUserPassword").show();
          }
      }
      $(function () {
        $(\'[name="new[AuthType]"]\').change(showHide);
      });

      window.onload = function() {
        showHide()
      };
    </script>';

    $this->renderScripts($scriptToHideShow);

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
    $LIBEXECDIR = $GLOBALS['SysConf']['DIRECTORIES']['LIBEXECDIR'];

    /* Compare new and old array
     * and update DB with new values */
    $UpdateMsg = "";
    $ErrorMsg="";
    if (! empty($newarray)) {
      foreach ($newarray as $VarName => $VarValue) {
        if ($VarValue != $oldarray[$VarName]) {
          /* get validation_function row from fossdashconfig table */
          $sys_array = $this->dbManager->getSingleRow("select validation_function, ui_label from fossdashconfig where variablename=$1",array($VarName),__METHOD__.'.getVarNameData');
          $validation_function = $sys_array['validation_function'];
          $ui_label = $sys_array['ui_label'];
          $is_empty = empty($validation_function);
          /* 1. the validation_function is empty
           2. the validation_function is not empty, and after checking, the value is valid
          update fossdashconfig table
          */
          if ($is_empty || (! $is_empty && (1 == $validation_function($VarValue)))) {
            $this->dbManager->getSingleRow(
              "update fossdashconfig set conf_value=$1 where variablename=$2",
              array($VarValue, $VarName), __METHOD__ . '.setVarNameData');
            if ($VarName == "FossdashEnableDisable") {
                $exec_fossdash_configuration_cmd = "python3 ".$LIBEXECDIR."/fossdash-publish.py fossdash_configure " . $VarValue;
                $output = shell_exec($exec_fossdash_configuration_cmd);
                file_put_contents('php://stderr', "output of the cmd for fossology_configuration(enable/Disable) changed ={$output} \n");
            } elseif ($VarName == "FossologyInstanceName") {
              $parameterName = "uuid";
              $exec_script_uuid_cmd = "python3 ".$LIBEXECDIR."/fossdash-publish.py " . $parameterName;
              $output = shell_exec($exec_script_uuid_cmd);
              file_put_contents('php://stderr', "output of cmd for fossology_instance_name changed ={$output} \n");
            } elseif ($VarName == "FossDashScriptCronSchedule") {
              $parameterName = "cron";
              $exec_script_cron_cmd = "python3 ".$LIBEXECDIR."/fossdash-publish.py " . $parameterName;
              $output = shell_exec($exec_script_cron_cmd);
              file_put_contents('php://stderr', "output of cmd for cron job changed  ={$output} \n");
            }

            if (! empty($UpdateMsg)) {
              $UpdateMsg .= ", ";
            }
            $UpdateMsg .= $VarName;
          } else if (! $is_empty && (0 == $validation_function($VarValue))) {
            /*
             * the validation_function is not empty, but after checking, the value
             * is invalid
             */
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
}

$NewPlugin = new FossdashConfig;
$NewPlugin->Initialize();