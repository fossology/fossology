<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.

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

use Fossology\Lib\Db\DbManager;

define("TITLE_ui_about", _("About FOSSology"));
define("_PROJECT", _("FOSSology"));
define("_COPYRIGHT", _("Copyright (C) 2007-2014 Hewlett-Packard Development Company, L.P.<br>
                        Copyright (C) 2014 Siemens AG."));
define("_TEXT", _("This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.\nThis program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.\nYou should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA."));

/**
 * \class ui_about extends FO_Plugin
 * \brief about page on UI
 */
class ui_about extends FO_Plugin
{
  var $_Project = _PROJECT;
  var $_Copyright=_COPYRIGHT;
  var $_Text=_TEXT;

  function __construct()
  {
    $this->Name       = "about";
    $this->Title      = TITLE_ui_about;
    $this->MenuList   = "Help::About";
    $this->DBaccess   = PLUGIN_DB_NONE;
    $this->LoginFlag  = 0;
    parent::__construct();
  }
  
  /**
   * \brief Generate output for this plug-in.
   */ 
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V = '';
    switch($this->OutputType)
    {
      case "XML":
        $V .= "<project>$this->_Project</project>\n";
        $V .= "<copyright>$this->_Copyright</copyright>\n";
        $V .= "<text>$this->_Text</text>\n";
        break;
      case "HTML":
        $V = $this->htmlContent();
        break;
      case "Text":
        $V .= "$this->_Project\n";
        $V .= "$this->_Copyright\n";
        $V .= str_replace("\n","\n\n",$this->_Text) . "\n";
        break;
      default:
        break;
    }
    $this->vars['content'] = $V;
  }
  
  protected function htmlContent()
  {
    global $VERSION;
    global $SVN_REV;
    $text = _("FOSSology version");
    $text1 = _("code revision");
    $html = "<b>$text $VERSION ($text1 $SVN_REV)</b>\n";
    global $container;
    $dbManager = $container->get('db.manager');
    $licenseRefTable = $dbManager->getSingleRow("SELECT COUNT(*) cnt FROM license_ref WHERE rf_text!=$1" ,array("License by Nomos."));
    $html .= " with $licenseRefTable[cnt] known licenses in database";
    $html .= "<P/>\n";
    $html .= "$this->_Copyright<P>\n";
    $html .= str_replace("\n","\n<P>\n",$this->_Text);
    return $html;
  }
}
$NewPlugin = new ui_about;
$NewPlugin->Initialize();
