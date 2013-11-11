<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_demohello", _("Demo Hello World"));

/* You can find the FO_Plugin class in src/www/ui/template/ */
class demohello extends FO_Plugin
{
  var $Name       = "demohello";
  var $Version    = "1.0";
  var $Title      = TITLE_demohello;
  var $MenuList   = "Help::Demo::Hello World";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;

  /**
   * \brief Find out who I am from my user record.
   * \returns user name
   */
  function WhoAmI()
  {
    $user_pk = $_SESSION['UserId'];

    if (empty($user_pk))
    {
      /* Note that this message is localized.  This is a good practice for as
       * much of your UI text as possible.
       */
      return _("You are not logged in");
    }

    /* The user's name is stored in $_SESSION[User] after they login.
     * But to demonstrate a database call, I'll get the name from the
     * user's primary key (in $_SESSION[UserId]).
     */
    $UserRow = GetSingleRec('users', "where user_pk='$_SESSION[UserId]'");
    return $UserRow['user_name'];
  } // end of WhoAmI()

  /**
   * \brief Generate output.
   */
  function Output()
  {
    /* make sure this plugin hasn't been turned off */
    if ($this->State != PLUGIN_STATE_READY) { return; }

    $UserName = $this->WhoAmI();
    $Hello = _("Hello");
    $OutBuf = "<h2>$Hello $UserName </h2>";
    $OutBuf .= _("Wasn't that easy?");

    print($OutBuf);
    return;
  } // Output()

};

$NewPlugin = new demohello;
$NewPlugin->Initialize();
?>
