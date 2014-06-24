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
  var $vars=array();
  /**
   * \brief Generate output
   * \param template name
   * \return output
   */
  function renderTemplate($templateName){
    $filename = 'template/' . $templateName . '.htm';
    if (!file_exists($filename))
    {
      $this->makeTemplate('template/' . $templateName . '.htc');
    }
    if (!file_exists($filename))
    {
      return 'Unknown template';
    }
    // return $filename. file_get_contents ($filename);
    ob_start();
    require_once($filename);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }
  
  /*
   * rewrite all templates
   */
  function updateTemplates($folder='template',$extension='htc') {
    foreach(glob("$folder/*.$extension") as $file) {
      $this->makeTemplate($file);
    }
  }

  /*
   * make *.php from *.htc by tranforming i18n tags and {{ }} 
   */
  function makeTemplate($filename){
    if (!file_exists($filename))
    {
      return false;
    }
    $output = file_get_contents($filename);
    $output = $this->parseI18n($output);
    $output = $this->parseVars($output);
    $phpfilename = preg_replace('/\\.[^.\\s]{3,4}$/', '.htm', $filename);
    $success = file_put_contents($phpfilename,$output);
    return (false!==$success);
  }
  
  /**
   * \brief Translate output
   * \param text with i18n tag
   * \return translation if input is well-structured
   */
  function parseI18n($haystack)
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
        return $res.'<?php echo _("'.htmlspecialchars(substr($haystack,$offset)).'"); ?>';
      }
      $res .= '<?php echo _("'.htmlspecialchars(substr($haystack,$offset,$close-$offset)).'"); ?>';
      $offset = $close+7; // strlen('</i18n>')==7
    }
    return $res.substr($haystack,$offset);
  }
  
  /**
   * \brief evaluates variables in format {{ x }}
   */
  function parseVars($haystack){
    $res = '';
    $offset = 0;
    while (false!==($open=strpos($haystack,'{{ ',$offset)))
    {
      $res .= substr($haystack,$offset,$open-$offset);
      $offset = $open+3; // strlen('{{ ')==3
      $close = strpos($haystack,' }}',$offset);
      /* $close>=6 is sure, but this way no missleading */
      if (!$close && !strpos($haystack,'{{ ',$offset))
      {
        return $haystack;
      }
      if (!$close)
        $key = substr($haystack,$offset);
      else
        $key = substr($haystack,$offset,$close-$offset);
      $res .= $this->translateVar($key);
      if(!$close)
        return res;
      $offset = $close+3; // strlen(' }}')==3
    }
    return $res.substr($haystack,$offset);
  }
  
  /*
   * \brief handles expression within {{ }}
   */
  function translateVar($subject){
    if (preg_match($pattern='/^[a-zA-Z0-9]+$/', $subject) )
      $var = '$renderer->vars["'.$subject.'"]';
    else if (preg_match($pattern='/^([a-zA-Z0-9]+)\.([a-zA-Z0-9]+)$/', $subject, $matches) ){
      $var = '$renderer->vars["'.$matches[1].'"]["'.$matches[2].'"]';
    }
    return "<?php echo $var; ?>";
  }
}
$renderer = new FO_Renderer();
if ('m'==@$argv[1]){
 $renderer->updateTemplates();
}