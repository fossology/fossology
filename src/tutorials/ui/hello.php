<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * \class  ui_hello extends from FO_Plugin
 * \biref This is the class name (ui_hello) and 
 * it extends functionality of the FO_Plugin
 */
class ui_hello extends FO_Plugin
{                                          
  public $Name       = "hello";                 /* This is the name by which FOSSology identifies the plugin */
  public $Title      = "Hello World Example";   /* This is the title that will be displayed in the UI */
  public $MenuList   = "Help::Hello World";     /* This is the description that will be displayed in the pulldown menu */
  public $LoginFlag  = 0;                       /* You do not need to be logged into the UI to execute this plugin */

  protected $_Text="Hello World";               /* This is the output message that will be displayed in the UI */

  /**
   * \brief Generate the text for this plugin. 
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }   /* State is set by FO_Plugin */
    $V="";
    switch($this->OutputType)                             /* OutputType is set by FO_Plugin */

    {
      case "XML":
        $V .= "<text>$this->_Text</text>\n";
        break;
      case "HTML":
        $V .= "<b>$this->_Text</b>\n";
        break;
      case "Text":
        $V .= "$this->_Text\n";
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }   /* OutputToStdout is a function defined by FO_Plugin */
    print($V);
    return;
  }

};
$NewPlugin = new ui_hello;
$NewPlugin->Initialize();                    /* Initialize is a function defined by FO_Plugin */
?>
