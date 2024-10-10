<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

class MenuRenderer
{
   /**
   * @param array $menu   menu list need to show as list
   * @param string $parm  a list of parameters to add to the URL.
   * @param int $uploadId upload id
   * @param int $folderId Folder ID for action items
   */
  public static function menuToActiveSelect($menu, &$parm, $uploadId = "", $folderId = 0)
  {
    $agentRequiringFolderId = ["ui_reportImport", "ui_fodecisionimporter"];
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

        $value = Traceback_uri() . '?mod=' . $Val->URI . '&' . $parm;
        if ($folderId != 0 && in_array($Val->URI, $agentRequiringFolderId)) {
          $value .= '&folder=' . $folderId;
        }
        $entry = '<option value="' . $value . '"';
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

    return '<select class="goto-active-option form-select-sm"><option disabled selected>-- select action --</option>'.$optionsOut.'</select>';
  }
}
