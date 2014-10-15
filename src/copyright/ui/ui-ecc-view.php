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

define("TITLE_ecc_view", _("View Export Control and Customs Analysis"));

class ecc_view extends Xpview
{
  function __construct()
  {
    $this->Name = "ecc-view";
    $this->Title = TITLE_ecc_view;
    $this->decisionTableName = "ecc_decision";
    $this->tableName = "ecc";
    $this->modBack = 'copyright-hist'; //TODO
    $this->hightlightTypeToStringMap= array(Highlight::ECC => 'Export Restriction');
    $this->typeToHighlightTypeMap =  array('ecc' => Highlight::ECC);
    parent::__construct();

    $this->vars['xptext'] = 'export restriction';
  }
}
$NewPlugin = new ecc_view;
$NewPlugin->Initialize();
