<?php
/*
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
*/

namespace Fossology\Lib\View;

class RendererTest extends \PHPUnit_Framework_TestCase
{
 
  public function testRenderTemplate()
  {
    $filename = dirname(__FILE__).'/plain.htm';
    $filehandler = fopen($filename,"w+");
    $cont = "Five is <?php echo (2+4);?>.";
    fwrite($filehandler,$cont);
    fclose($filehandler);

    $renderer = new Renderer(dirname(__FILE__).'/');
    $txt = $renderer->renderTemplate('plain');
    assertThat($txt,is('Five is 6.'));
    
    unlink($filename);
    
    $notxt = $renderer->renderTemplate('unknown');
    assertThat($notxt,is('Unknown template'));    
  }

  public function testRenderTemplateIfNotPrepared()
  {
    $filenamePure = dirname(__FILE__).'/plain';
    $filehandler = fopen($filenamePure.'.htc',"w+");
    $cont = "Six is 7.";
    fwrite($filehandler,$cont);
    fclose($filehandler);
    if (file_exists($filenamePure . '.htm'))
    {
      unlink($filenamePure . '.htm');
    }

    $renderer = new Renderer(dirname(__FILE__).'/');
    $txt = $renderer->renderTemplate('plain');
    assertThat($txt,is('Six is 7.'));
    assertThat(file_exists($filenamePure.'.htm'),true);
    
    unlink($filenamePure.'.htc');
    unlink($filenamePure.'.htm');
  }
  
  
  public function testMakeTemplateVar()
  {
    $filenamePure = dirname(__FILE__).'/plain';
    $filehandler = fopen($filenamePure.'.htc',"w+");
    $cont = "Seven is {{ eight }}.";
    fwrite($filehandler,$cont);
    fclose($filehandler);
    if (file_exists($filenamePure . '.htm'))
    {
      unlink($filenamePure . '.htm');
    }

    $renderer = new Renderer(dirname(__FILE__).'/');
    $renderer->makeTemplate($filenamePure . '.htc');
    $txt = file_get_contents($filenamePure.'.htm');
    assertThat($txt,is('Seven is <?php echo $this->vars["eight"]; ?>.'));
    
    unlink($filenamePure.'.htc');
    unlink($filenamePure.'.htm');
  }
  
  public function testMakeTemplateI18n()
  {
    $filenamePure = dirname(__FILE__).'/plain';
    $filehandler = fopen($filenamePure.'.htc',"w+");
    $cont = "Eight is <b><i18n>nine</i18n>.</b>";
    fwrite($filehandler,$cont);
    fclose($filehandler);
    if (file_exists($filenamePure . '.htm'))
    {
      unlink($filenamePure . '.htm');
    }

    $renderer = new Renderer(dirname(__FILE__).'/');
    $renderer->makeTemplate($filenamePure . '.htc');
    $txt = file_get_contents($filenamePure.'.htm');
    assertThat($txt,is('Eight is <b><?php echo _("nine"); ?>.</b>'));
    
    unlink($filenamePure.'.htc');
    unlink($filenamePure.'.htm');
  }
  
  public function testMakeTemplateIf()
  {
    $filenamePure = dirname(__FILE__).'/plain';
    $filehandler = fopen($filenamePure.'.htc',"w+");
    $cont = "Nine is {% if x %}six{% endif %}teen.";
    fwrite($filehandler,$cont);
    fclose($filehandler);
    if (file_exists($filenamePure . '.htm'))
    {
      unlink($filenamePure . '.htm');
    }

    $renderer = new Renderer(dirname(__FILE__).'/');
    $renderer->makeTemplate($filenamePure . '.htc');
    $txt = file_get_contents($filenamePure.'.htm');
    assertThat($txt,is('Nine is <?php if($this->vars["x"]) { ?>six<?php } ?>teen.'));
    
    unlink($filenamePure.'.htc');
    unlink($filenamePure.'.htm');
  }

  
  public function testCreateSelect()
  {
    $renderer = new Renderer();
    $result = $renderer->createSelect($id='ok',$options=array('k1'=>'v1','k2'=>'v2'),$select="k2",$actions=" ");
    $inner = '<option value="k1">v1</option><option value="k2" selected>v2</option>';
    $expected = "<select name=\"$id\" id=\"$id\" $actions>$inner</select>";
    assertThat($result, is($expected));
  }

}
