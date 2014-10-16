<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Johannes Najjar

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
 */
use Fossology\Lib\Data\Highlight;

define("TITLE_ip_view", _("View patent Analysis"));

class ip_view extends Xpview
{
  function __construct()
  {
    $this->Name = "ip-view";
    $this->Title = TITLE_ip_view;
    $this->decisionTableName = "ip_decision";
    $this->tableName = "ip";
    $this->modBack = 'copyright-hist';//TODO
    $this->optionName = "skipFileIp";
    $this->ajaxAction = "setNextPrevIp";
    $this->skipOption = "noIp";
    $this->hightlightTypeToStringMap= array(Highlight::IP => 'Patent');
    $this->typeToHighlightTypeMap =  array('patent' => Highlight::IP);
    parent::__construct();
    $this->vars['xptext'] = 'patent';
  }
}
$NewPlugin = new ip_view;
$NewPlugin->Initialize();
