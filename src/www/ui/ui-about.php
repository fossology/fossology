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

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

define("_PROJECT", _("FOSSology"));
define("_COPYRIGHT", _("Copyright (C) 2007-2014 Hewlett-Packard Development Company, L.P.<br>
                        Copyright (C) 2014 Siemens AG."));
define("_TEXT", _("This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.\nThis program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.\nYou should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA."));

/**
 * \class ui_about extends FO_Plugin
 * \brief about page on UI
 */
class ui_about extends DefaultPlugin
{
  var $_Project = _PROJECT;

  function __construct()
  {
    parent::__construct("about", "About Fossology");
    $this->MenuList   = "Help::About";
    $this->DBaccess   = PLUGIN_DB_NONE;
    $this->LoginFlag  = 0;
  }
  
  /**
   * @param Request $request
   * @return Response
   */
  protected function handleRequest(Request $request)
  {
    global $VERSION;
    global $SVN_REV;
    $dbManager = $this->getObject('db.manager');
    $licenseRefTable = $dbManager->getSingleRow("SELECT COUNT(*) cnt FROM license_ref WHERE rf_text!=$1" ,array("License by Nomos."));

    $vars = array(
      'version' => $VERSION,
      'revision' => $SVN_REV,
      'licenseCount' => $licenseRefTable['cnt'],
      'copyright' => _COPYRIGHT,
      'text' => _TEXT
    );

    return $this->render('about.twig.html', $vars);
  }
}

$NewPlugin = new ui_about;
$NewPlugin->initialize();
