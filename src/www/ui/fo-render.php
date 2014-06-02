<?php
/***********************************************************
Copyright (C) 2014, Siemens AG

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
 ***********************************************************/

/* light weight renderer */
class FO_Renderer
{
  var $language='en';
  /**
   * \brief Generate output
   * \param template name
   * \return output
   */
  function renderTemplate($templateName){
    $filename = 'template/' . $templateName . '.htc';
    if (!file_exists($filename))
    {
      return 'Unknown template';
    }
    // return $filename. file_get_contents ($filename);
    ob_start();
    require_once($filename);
    $output = ob_get_contents();
    ob_end_clean();
    return $this->translateI18n($output);
  }
  
  /**
   * \brief Translate output
   * \param text with i18n tag
   * \return translation if input is well-structured
   */
  function translateI18n($haystack)
  {
    $res = '';
    $offset = 0;
    while (false!==($open=strpos($haystack,'<i18n>',$offset)))
    {
      $res .= substr($haystack,$offset,$open-$offset);
      $offset = $open+6; // strlen('<i18n>')==6
      $close = strpos($haystack,'</i18n>',$offset);
      /* $close>=6 is sure, but this way no missleading */
      if (!$close && !strpos($haystack,'<i18n>',$offset))
      {
        return $haystack;
      }
      if (!$close)
      {
        return $res._(substr($haystack,$offset));
      }
      $res .= _(substr($haystack,$offset,$close-$offset));
      $offset = $close+7; // strlen('</i18n>')==6
    }
    return $res.substr($haystack,$offset);
  }
}
$renderer = new FO_Renderer();