<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

class MenuRenderer
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

    foreach ($menu as $Val) {
      if (!empty($Val->HTML)) {
        $entry = $Val->HTML;
      } else if (!empty($Val->URI)) {
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
      } else {
        $entry = "<option>" . $Val->getName($showFullName) . "</option>";
      }
      $optionsOut .= $entry;
    }

    if (plugin_find_id('showjobs') >= 0) {
      $optionsOut .= '<option value="' . Traceback_uri() . '?mod=showjobs&upload='.$uploadId.'" title="' . _("Scan History") . '" >'._("History").'</option>';
    }

    return '<select class="goto-active-option form-control-sm"><option disabled selected>-- select action --</option>'.$optionsOut.'</select>';
  }
}
