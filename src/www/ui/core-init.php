<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

use Fossology\Lib\Auth\Auth;

define("TITLE_core_init", _("Initialize"));

class core_init extends FO_Plugin
{
  var $Name       = "init";
  var $Title      = TITLE_core_init;
  var $Version    = "1.0";
  var $MenuList   = "Admin::Initialize";
  var $Dependency = array("auth","refresh","menus","Default");
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;
  var $PluginLevel= 100; /* make this run first! */

  /**
   * \brief This is where the magic for
   * mod=init happens.
   * This plugin only runs when the special file
   * "..../www/init.ui" exists!
   */
  function PostInitialize()
  {
    if ($this->State != PLUGIN_STATE_VALID) { return(1); } // don't re-run
    /** Disable everything but me, DB, menu **/
    /* Enable or disable plugins based on login status */
    global $Plugins;
    $Filename = getcwd() . "/init.ui";
    if (!file_exists($Filename))
    {
      $this->State = PLUGIN_STATE_INVALID;
      return;
    }
    $Max = count($Plugins);
    for($i=0; $i < $Max; $i++)
    {
      $P = &$Plugins[$i];
      if ($P->State == PLUGIN_STATE_INVALID) { continue; }
      /* Don't turn off plugins that are already up and running. */
      if ($P->State == PLUGIN_STATE_READY) { continue; }
      if ($P->DBaccess == PLUGIN_DB_ADMIN) { continue; }
      $Key = array_search($P->Name,$this->Dependency);
      if (($Key === FALSE) && strcmp($P->Name,$this->Name))
      {
        // print "Disable " . $P->Name . " as $Key <br>\n";
        $P->Destroy();
        $P->State = PLUGIN_STATE_INVALID;
      }
      else
      {
        // print "Keeping " . $P->Name . " as $Key <br>\n";
      }
    }
    $this->State = PLUGIN_STATE_READY;
    if (($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_ADMIN) && ($this->MenuList !== ""))
    {
      menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
    }
    return($this->State == PLUGIN_STATE_READY);
  } // PostInitialize()

  /**
   * \brief This is only called when the user logs out.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* If you are not logged in, then force a login. */
        if (empty($_SESSION['User']))
        {
          $P = &$Plugins["auth"];
          $P->OutputSet($this->OutputType,0);
          $V .= $P->Output();
          $P->OutputUnSet();
        }
        else /* It's an init */
        {
          $FailFlag=0;
          $Filename = getcwd() . "/init.ui";
          $Schema = &$Plugins["schema"];
          if (empty($Schema))
          {
            $V .= _("Failed to find schema plugin.\n");
            $FailFlag = 1;
          }
          else
          {
            print "<pre>";
            $FailFlag = $Schema->ApplySchema($Schema->Filename,0,0);
            print "</pre>";
          }
          if (!$FailFlag)
          {
            $V .= _("Initialization complete.  Click 'Home' in the top menu to proceed.<br />");
            if (is_writable(getcwd())) { $State = unlink($Filename); }
            else { $State = 0; }
            if (!$State)
            {
              $V .= "<font color='red'>";
              $V .= _("Failed to remove $Filename\n");
              $text = _("Remove this file to complete the initialization.\n");
              $V .= "<br />$text";
              $V .= "</font>\n";
              $FailedFlag = 1;
            }
          }
          else
          {
            $V .= "<font color='red'>";
            $V .= _("Initialization complete with errors.");
            $V .= "</font>\n";
          }
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  } // Output()
  
  /**
   * @see http://www.fossology.org/projects/fossology/wiki/UI_Architecture_Overview
   */
  function infoFirstTimeUsage()
  {
    $text = _("The system requires initialization. Please login and use the Initialize option under the Admin menu.");
    $V .= "<b>$text</b>";
    $V .= "<P />\n";
    /* Check for a default user */
    global $PG_CONN;
    $Level = PLUGIN_DB_ADMIN;
    $sql = "SELECT * FROM users WHERE user_perm = $Level LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);
    if (array_key_exists("user_seed", $R) && array_key_exists("user_pass", $R))
    {
      $sql = "SELECT user_name FROM users WHERE user_seed IS NULL AND user_pass IS NULL";
    }
    else
    {
      $sql = "SELECT user_name FROM users";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!empty($R['user_name']))
    {
      $V .= _("If you need an account, use '" . $R['user_name'] . "' with no password.\n");
      $V .= "<P />\n";
    }
    return $V;
  }

}
$NewPlugin = new core_init;
$NewPlugin->Initialize();
