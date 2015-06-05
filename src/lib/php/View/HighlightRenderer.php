<?php
/*
Copyright (C) 2014-2015, Siemens AG
Authors: Andreas Würl, Daniele Fognini

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

namespace Fossology\Lib\View;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\SplitPosition;
use Fossology\Lib\Util\Object;

/**
 * Class HighlightRenderer
 * @package Fossology\Lib\View
 */
class HighlightRenderer extends Object
{
  const DEFAULT_PADDING = 0;
  
  public $classMapping = array('' => '',
            Highlight::UNDEFINED=>'hi-undefined',
          
            Highlight::MATCH => 'hi-match',
            Highlight::CHANGED => 'hi-changed',
            Highlight::ADDED => 'hi-added',
            Highlight::DELETED => 'hi-deleted',
            Highlight::SIGNATURE => 'hi-signature',
            Highlight::KEYWORD => 'hi-keyword',
            Highlight::BULK => 'hi-bulk',
            Highlight::COPYRIGHT => 'hi-cp',
            Highlight::EMAIL => 'hi-email',
            Highlight::URL => 'hi-url',
            Highlight::AUTHOR => 'hi-author',
            Highlight::BULK => 'hi-bulk',
            Highlight::IP => 'hi-ip',
            Highlight::ECC => 'hi-mediumorchid'
          );
  
  /**
   * @param SplitPosition $entry
   * @return string
   */
  public function createSpanStart(SplitPosition &$entry)
  {
    $depth = $entry->getLevel();
    $highlight = $entry->getHighlight();
    $type = $highlight ? $highlight->getType() : Highlight::UNDEFINED;

    $wrappendElement = "";

    if ($highlight)
    {
      $htmlElement = $highlight->getHtmlElement();
      if ($htmlElement)
      {
        $wrappendElement = $htmlElement->getOpeningText();
      }
    }

    return $this->createStyleWithPadding($type, $highlight->getInfoText(), $depth) . $wrappendElement;
  }

  /**
   * @param SplitPosition $entry
   * @return string
   */
  public function createSpanEnd(SplitPosition $entry)
  {
    $highlight = $entry->getHighlight();

    $wrappendElement = "";

    if ($highlight)
    {
      $htmlElement = $highlight->getHtmlElement();
      if ($htmlElement)
      {
        $wrappendElement = $htmlElement->getClosingText();
      }
    }

    return $wrappendElement . '</span>';
  }

  /**
   * @param $type
   * @param $title
   * @param int $depth
   * @return string
   */
  public function createStyleWithPadding($type, $title, $depth = 0)
  {
    $style = $this->createStartSpan($type, $title);
    if ($depth < self::DEFAULT_PADDING)
    {
      $padd = (2 * (self::DEFAULT_PADDING - $depth - 2)) . 'px';
      return $this->getStyleWithPadding($padd, $style);
    } else
    {
      return $style;
    }
  }

  /**
   * @param $padding
   * @param $style
   * @internal param $out
   * @return string
   */
  public function getStyleWithPadding($padding, $style)
  {
    return str_replace('background', "padding-top:$padding;padding-bottom:$padding;background", $style);
  }

  /**
   * @param string $type
   * @param string $title
   * @return string
   */
  public function createStartSpan($type, $title)
  {
    if ($type == 'K ' || $type == 'K')
    {
      return "<span class=\"hi-keyword\">";
    }
    if (!array_key_exists($type, $this->classMapping))
    {
      $type = Highlight::UNDEFINED;
    }
    $class = $this->classMapping[$type];
    return "<span class=\"$class\" title=\"$title\">";
  }

  /**
   * @param boolean $containsDiff
   * @return array
   */
  public function getLegendData($containsDiff)
  {
    $data = array();

    $colorDefinition = $containsDiff
        ? array(
            '' => _('license text:'),
            Highlight::MATCH => _('&nbsp;- identical'),
            Highlight::CHANGED => _('&nbsp;- modified'),
            Highlight::ADDED => _('&nbsp;- added'),
            Highlight::DELETED => _('&nbsp;- removed'),
            Highlight::SIGNATURE => _('license relevant text'),
            Highlight::KEYWORD => _('keyword'),
            Highlight::BULK => _('bulk'))
        : array(
            Highlight::UNDEFINED => _("license relevant text"));
    foreach ($colorDefinition as $colorKey => $txt)
    {
      $data[] = array('class'=>$this->classMapping[$colorKey], 'text' => $txt);
    }
    return $data;
  }

} 
