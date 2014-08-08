<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

class PagedHexResult extends PagedResult
{
  const BYTES_PER_LINE = 16;

  /**
   * @var string
   */
  private $currentHexText;

  /**
   * @var string[]
   */
  private $hexTexts;

  /**
   * @var string
   */
  private $charText;

  /**
   * @var int
   */
  private $charCount;

  /**
   * @var int
   */
  private $lineCount;
  /**
   * @var HighlightState
   */
  private $highlightState;

  /**
   * @param $startOffset
   * @param HighlightState $highlightState
   */
  public function __construct($startOffset, HighlightState $highlightState)
  {
    parent::__construct($startOffset);
    $this->highlightState = $highlightState;

    $this->resetLineData();
    $this->lineCount = 0;
  }

  public function getText()
  {
    $text = parent::getText();

    if ($this->charCount > 0)
    {
      $text .= $this->createHexdumpLine();
    }
    return $text;
  }

  public function appendMetaText($text)
  {
    if (strlen($text) > 0)
    {
      $this->charText .= $text;
      $this->currentHexText .= $text;
    }
  }

  /**
   * @param string $text
   * @return string
   */
  protected function renderContentText($text)
  {
    do
    {
      $usableCharacters = min(self::BYTES_PER_LINE - $this->charCount, strlen($text));
      $usedCharacters = substr($text, 0, $usableCharacters);
      $text = substr($text, $usableCharacters);
      $escapedText = $this->encodeCharacters($usedCharacters);
      $this->charText .= preg_replace("/\\s/", "&nbsp;", $escapedText);
      $asHexStrings = $this->asHexStrings($usedCharacters);
      if (strlen($this->currentHexText) > 0)
      {
        $this->mergeMetaText($asHexStrings, 0);
      }
      $this->hexTexts = array_merge($this->hexTexts, $asHexStrings);
      $this->charCount += strlen($usedCharacters);

      if ($this->charCount == self::BYTES_PER_LINE)
      {
        $this->highlightState->closeOpenElements($this);
        if (strlen($this->currentHexText) > 0)
        {
          $this->mergeMetaText($this->hexTexts, count($this->hexTexts) - 1, false);
        }

        $result = $this->createHexdumpLine();
        parent::appendMetaText($result . "<br/>\n");
        $this->resetLineData();
        $this->highlightState->openExistingElements($this);
        $this->lineCount++;
      }
    } while ($text);

    return "";
  }

  /**
   * @param $text
   * @return string[]
   */
  private function asHexStrings($text)
  {
    $hexValues = array();
    for ($i = 0; $i < strlen($text); $i++)
    {
      $hexValues[] = sprintf("%02x", ord($text[$i]));
    }
    return $hexValues;
  }

  /**
   * @return string
   */
  protected function createHexdumpLine()
  {
    $missingCharacters = self::BYTES_PER_LINE - $this->charCount;
    $charTextFill = str_repeat("&nbsp;", $missingCharacters);
    $hexTextFill = str_repeat(" __", $missingCharacters);

    $hexText = implode(" ", $this->hexTexts) . $hexTextFill;
    $charText = $this->charText . $charTextFill;
    $currentOffset = $this->getStartOffset() + $this->lineCount * self::BYTES_PER_LINE;
    return "0x" . sprintf("%08X", $currentOffset) . " |" . $hexText . "| |" . $charText . "|";
  }

  protected function resetLineData()
  {
    $this->currentHexText = "";
    $this->hexTexts = array();
    $this->charText = "";
    $this->charCount = 0;
  }

  /**
   * @param $targetArray
   * @param $targetIndex
   * @param bool $prependMeta
   * @return mixed
   */
  protected function mergeMetaText(&$targetArray, $targetIndex, $prependMeta = true)
  {
    $targetArray[$targetIndex] =
        $prependMeta
            ? $this->currentHexText . $targetArray[$targetIndex]
            : $targetArray[$targetIndex] . $this->currentHexText;
    $this->currentHexText = "";
  }

  /**
   * @param $usedCharacters
   * @return string
   */
  protected function encodeCharacters($usedCharacters)
  {
    $encodedText = "";
    for ($i = 0; $i < strlen($usedCharacters); $i++)
    {
      $character = $usedCharacters[$i];
      $encodedText .= ctype_print($character) || ctype_space($character) ? htmlspecialchars($character) : '?';
    }
    return $encodedText;
  }

}