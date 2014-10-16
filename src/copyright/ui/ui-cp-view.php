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


/**
 * \file ui-cp-view.php
 * \brief View Copyright/Email/Url Analysis on an Analyzed file
 */

define("TITLE_copyright_view", _("View Copyright/Email/Url Analysis"));

class copyright_view extends Xpview
{

  function __construct()
  {
    $this->Name = "copyright-view";
    $this->Title = TITLE_copyright_view;
    $this->decisionTableName = "copyright_decision";
    $this->tableName = "copyright";
    $this->modBack = 'copyright-hist';
    $this->optionName = "skipFileCopyRight";
    $this->ajaxAction = "setNextPrevCopyRight";
    $this->skipOption = "noCopyright";
    $this->hightlightTypeToStringMap= array(Highlight::COPYRIGHT => 'copyright remark',
        Highlight::URL => 'URL', Highlight::EMAIL => 'e-mail address');
    $this->typeToHighlightTypeMap = array(
        'statement' => Highlight::COPYRIGHT,
        'email' => Highlight::EMAIL,
        'url' => Highlight::URL);
    parent::__construct();

    $this->vars['xptext'] = 'copyright/e-mail/URL';
  }



}

$NewPlugin = new copyright_view;
$NewPlugin->Initialize();
