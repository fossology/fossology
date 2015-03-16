<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_foconfig", _("Configuration Variables"));

/**
 * \class foconfig extend from FO_Plugin
 * \brief display and set FOSSology configuration
 */
class foconfig extends FO_Plugin
{
  var $CreateAttempts = 0;

  function __construct()
  {
    $this->Name       = "foconfig";
    $this->Title      = TITLE_foconfig;
    $this->MenuList   = "Admin::Customize";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    $this->PluginLevel = 50;    // run before 'regular' plugins
    parent::__construct();
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
    $OutBuf .= "<form method='POST'>";
    while ($row = pg_fetch_assoc($result))
    {
      if ($Group != $row['group_name'])
      {
        if ($Group) $OutBuf .= "</table><br>";
        $Group = $row['group_name'];
        $OutBuf .= "<table border=1>";
      }

      $OutBuf .= "<tr><td>$row[ui_label]</td><td>";
      switch ($row['vartype'])
      {
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
        default:
          $OutBuf .= "Invalid configuration variable.  Unknown type.";
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
    global $PG_CONN;
    global $Plugins;

    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    if (empty($PG_CONN)) return;

    $newarray = GetParm("new", PARM_RAW);
    $oldarray = GetParm("old", PARM_RAW);

    /* Compare new and old array
     * and update DB with new values */
    $UpdateMsg = "";
    if (!empty($newarray))
    {
      foreach($newarray as $VarName => $VarValue)
      {
        if ($VarValue != $oldarray[$VarName])
        {
          /* get validation_function row from sysconfig table */
          $sql = "select validation_function, ui_label from sysconfig where variablename='".pg_escape_string($VarName)."';";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          $sys_array = pg_fetch_assoc($result);
          $validation_function = $sys_array['validation_function'];
          $ui_label = $sys_array['ui_label'];
          pg_free_result($result);
          $is_empty = empty($validation_function);
          /* 1. the validation_function is empty
           2. the validation_function is not empty, and after checking, the value is valid
          update sysconfig table
          */
          if ($is_empty || (!$is_empty && (1 == $validation_function($VarValue))))
          {
            $sql = "update sysconfig set conf_value='" .
            pg_escape_string($VarValue) .
              "' where variablename='$VarName'";
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
            pg_free_result($result);
            if (!empty($UpdateMsg)) $UpdateMsg .= ", ";
            $UpdateMsg .= $VarName;
          }
          /* the validation_function is not empty, but after checking, the value is invalid */
          else if (!$is_empty && (0 == $validation_function($VarValue)))
          {
            if (!strcmp($validation_function, 'check_boolean'))
            {
              $warning_msg = _("Error: You set $ui_label to $VarValue. Valid  values are \'true\' and \'false\'.");
              echo "<script>alert('$warning_msg');</script>";
            }
            else if  (strpos($validation_function, "url"))
            {
              $warning_msg = _("Error: $ui_label $VarValue, is not a reachable URL.");
              echo "<script>alert('$warning_msg');</script>";
            }
          }
        }
      }
      if (!empty($UpdateMsg)) $UpdateMsg .= " updated.";
    }

    $OutBuf = '';
    if($this->OutputType=='HTML')
    {
      if ($UpdateMsg) $OutBuf .= "<span style='background-color:#ff8a8a'>$UpdateMsg</style><hr>";
      $OutBuf .= $this->HTMLout();
    }
    $this->vars['content'] = $OutBuf;
  }

}
$NewPlugin = new foconfig;
$NewPlugin->Initialize();
