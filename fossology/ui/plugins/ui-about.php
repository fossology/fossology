<?php
/***********************************************************
 Copyright (C) 2008-2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class ui_about extends FO_Plugin
  {
  var $Name       = "about";
  var $Title      = "About FOSSology";
  var $Version    = "1.0";
  var $MenuList   = "Help::About";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  var $_Project="FOSSology";
  var $_Copyright="Copyright (C) 2007-2010 Hewlett-Packard Development Company, L.P.";
  var $_Text="This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.\nThis program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.\nYou should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.";

  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
$text = _("_Project");
$text1 = _("\n");
	$V .= "<project>$this->$text</project>$text1";
$text = _("_Copyright");
$text1 = _("\n");
	$V .= "<copyright>$this->$text</copyright>$text1";
$text = _("_Text");
$text1 = _("\n");
	$V .= "<text>$this->$text</text>$text1";
	break;
      case "HTML":
	global $VERSION;
	global $SVN_REV;
	$V .= "<b>FOSSology version $VERSION (code revision $SVN_REV)</b>\n";
	$V .= "<P/>\n";
	$V .= "$this->_Copyright<P>\n";
	$V .= str_replace("\n","\n<P>\n",$this->_Text);
	break;
      case "Text":
	$V .= _("$this->_Project\n");
	$V .= _("$this->_Copyright\n");
	$V .= str_replace(_("\n","\n\n",$this->_Text) . "\n");
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new ui_about;
$NewPlugin->Initialize();
?>
