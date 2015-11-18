<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\UI;

use Fossology\Lib\Util\Object;

class MenuRenderer extends Object
{
   /**
   * @param $menu     menu list need to show as list
   * @param $parm     a list of parameters to add to the URL.
   * @param $uploadId upload id
   */
  public static function menuToActiveSelect($menu, &$parm, $uploadId = "") 
  {
    if (empty($menu)) {
      return '';
    }

    $showFullName = isset($_SESSION) && array_key_exists('fullmenudebug', $_SESSION) && $_SESSION['fullmenudebug'] == 1;
    $optionsOut = "";

    foreach($menu as $Val) {
      if (!empty($Val->HTML)) {
        $entry = $Val->HTML;
      }
      else if (!empty($Val->URI)) {
        if (!empty($uploadId) && "tag" == $Val->URI) {
          $tagstatus = TagStatus($uploadId);
          if (0 == $tagstatus) {  // tagging on this upload is disabled
            break;
          }
        }

        $entry = '<option value="' . Traceback_uri() . '?mod=' . $Val->URI . '&' . $parm . '"';
        if (!empty($Val->Title)) {
          $entry .= ' title="' . htmlentities($Val->Title, ENT_QUOTES) . '"';
        }
        $entry .= '>'. $Val->getName($showFullName).'</option>';
      }
      else {
        $entry = "<option>" . $Val->getName($showFullName) . "</option>";
      }
      $optionsOut .= $entry;
    }
    
    if (plugin_find_id('showjobs') >= 0)
    {
      $optionsOut .= '<option value="' . Traceback_uri() . '?mod=showjobs&upload='.$uploadId.'" title="' . _("Scan History") . '" >'._("History").'</option>';
    }
    
    return '<select class="goto-active-option"><option>-- select action --</option>'.$optionsOut.'</select>';
 }
}

