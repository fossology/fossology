<?php
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

namespace Fossology\Lib\UI\Component;

class MicroMenu
{
  const VIEW = 'View';
  const VIEW_META = 'View-Meta';

  const FORMAT_HEX = 'hex';
  const FORMAT_TEXT = 'text';
  const FORMAT_FLOW = 'flow';

  const TARGET_DEFAULT = 'default';
  const TARGET_VIEW = 'view';

  private $formatOptions = array(self::FORMAT_FLOW, self::FORMAT_TEXT, self::FORMAT_HEX);

  private $textFormats = array(self::FORMAT_HEX, self::FORMAT_TEXT, self::FORMAT_FLOW);

  private $targets = array(
    self::TARGET_DEFAULT => array(MicroMenu::VIEW_META, MicroMenu::VIEW),
    self::TARGET_VIEW => array(MicroMenu::VIEW)
  );

  public function insert($groups, $name, $position, $module, $uri, $tooltip)
  {
    if (!is_array($groups)) {
      $groups = $this->targets[$groups];
    }

    $showLink = GetParm("mod", PARM_STRING) !== $module;

    foreach ($groups as $group) {
      $menuKey = $group . MENU_PATH_SEPARATOR . $name;
      if ($showLink) {
        menu_insert($menuKey, $position, $uri, $tooltip);
      } else {
        menu_insert($menuKey, $position);
      }
    }
  }

  /**
   * @param $itemId
   * @return string
   */
  public function getFormatParameter($itemId = null)
  {
    $selectedFormat = GetParm("format", PARM_STRING);

    if (in_array($selectedFormat, $this->formatOptions)) {
      return $selectedFormat;
    }
    if (empty($itemId)) {
      return self::FORMAT_FLOW;
    } else {
      $mimeType = GetMimeType($itemId);
      list($type, $dummy) = explode("/", $mimeType, 2);
      return $type == 'text' ? self::FORMAT_TEXT : self::FORMAT_FLOW;
    }
  }

  /**
   * @param $selectedFormat
   * @param $pageNumber
   * @param string $menuKey
   * @param int $hexFactor
   * @return string
   */
  public function addFormatMenuEntries($selectedFormat, $pageNumber, $menuKey = self::VIEW, $hexFactor = 10)
  {
    $uri = Traceback_parm();
    $uri = preg_replace("/&format=[a-zA-Z0-9]*/", "", $uri);
    $uri = preg_replace("/&page=[0-9]*/", "", $uri);

    $pageNumberHex = null;
    $pageNumberText = null;

    $tooltipTexts = array(
      self::FORMAT_HEX => _("View as a hex dump"),
      self::FORMAT_TEXT => _("View as unformatted text"),
      self::FORMAT_FLOW => _("View as formatted text")
    );

    $menuTexts = array(
      self::FORMAT_HEX => "Hex",
      self::FORMAT_TEXT => "Text",
      self::FORMAT_FLOW => "Formatted"
    );

    $menuPosition = -9;
    menu_insert("$menuKey::[BREAK]", $menuPosition--);

    foreach ($this->textFormats as $currentFormat) {
      $menuName = $menuKey . MENU_PATH_SEPARATOR . $menuTexts[$currentFormat];
      if ($currentFormat == $selectedFormat) {
        menu_insert($menuName, $menuPosition--);
      } else {
        $targetPageNumber = $currentFormat == self::FORMAT_HEX ? $hexFactor * $pageNumberHex : $pageNumber;
        menu_insert($menuName, $menuPosition--, "$uri&format=$currentFormat&pageNumber=$targetPageNumber", $tooltipTexts[$currentFormat]);
      }
    }
    menu_insert("$menuKey::[BREAK]", $menuPosition);
  }
}
