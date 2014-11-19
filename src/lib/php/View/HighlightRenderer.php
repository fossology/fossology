<?php
/*
Copyright (C) 2014, Siemens AG
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

  const DEFAULT_COLOR = 'lightgray';

  /**
   * @var array colorMapping
   */
  private $colorMapping = array(
      Highlight::MATCH => 'lightgreen',
      Highlight::CHANGED => 'yellow',
      Highlight::ADDED => 'red',
      Highlight::DELETED => 'fuchsia',
      Highlight::SIGNATURE => 'lightskyblue',
      Highlight::KEYWORD => 'black',
      Highlight::COPYRIGHT => 'lightblue',
      Highlight::EMAIL => 'yellow',
      Highlight::URL => 'orange',
      Highlight::BULK => '#EFBE76',
      Highlight::IP => '#FF7F50', // Coral
      Highlight::ECC => '#BA55D3', // MediumOrchid 

      Highlight::UNDEFINED => self::DEFAULT_COLOR
  );

  /**
   * @return array
   */
  public function getColorMapping()
  {
    return $this->colorMapping;
  }

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
      return "<span style=\"font-weight: bold\">";
    } else
    {
      $color = $this->determineColor($type);
      return $this->createHighlightSpanStart($color, $title);
    }
  }

  /**
   * @param string $type
   * @return string
   */
  public function createStyle($type)
  {
    if ($type == 'K ' || $type == 'K')
    {
      return "font-weight: bold";
    } else
    {
      $color = $this->determineColor($type);
      return "background-color:$color;";
    }
  }

  /**
   * @param string $color
   * @param string $title
   * @return string
   */
  private function createHighlightSpanStart($color, $title)
  {
    return "<span style=\"background-color:$color;\" title=\"" . $title . "\">";
  }

  /**
   * @param string $type
   * @return string
   */
  protected function determineColor($type)
  {
    if (array_key_exists($type, $this->colorMapping))
    {
      return $this->colorMapping[$type];
    } else
    {
      if (array_key_exists(Highlight::UNDEFINED, $this->colorMapping))
      {
        return $this->colorMapping[Highlight::UNDEFINED];
      } else
      {
        return self::DEFAULT_COLOR;
      }
    }
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
      $data[] = array('style' => $colorKey ? $this->createStyle($colorKey) : '', 'text' => $txt);
    }
    return $data;
  }

} 
