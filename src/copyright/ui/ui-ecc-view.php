<?php
/*
 Copyright (C) 2014-2015, Siemens AG
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

require_once('Xpview.php');

class EccView extends Xpview
{
  const NAME = 'ecc-view';

  function __construct()
  {
    $this->Name = self::NAME;
    $this->decisionTableName = "ecc_decision";
    $this->tableName = "ecc";
    $this->modBack = 'ecc-hist';
    $this->optionName = "skipFileEcc";
    $this->ajaxAction = "setNextPrevEcc";
    $this->skipOption = "noEcc";
    $this->hightlightTypeToStringMap = array(Highlight::ECC => 'Export Restriction');
    $this->typeToHighlightTypeMap = array('ecc' => Highlight::ECC);
    $this->xptext = 'export restriction';
    parent::__construct(self::NAME, array(
        self::TITLE => _("View Export Control and Customs Analysis")
    ));
  }
}

register_plugin(new EccView());