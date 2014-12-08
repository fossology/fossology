<?php
/*
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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

$wwwPath = dirname(dirname(__DIR__));
require_once(dirname($wwwPath).'/lib/php/Plugin/FO_Plugin.php');
if(!function_exists('register_plugin')){ function register_plugin(){}}
require_once ($wwwPath.'/ui/ui-picker.php');


class ui_picker_Test extends \PHPUnit_Framework_TestCase
{

  /**
   * \brief test for Uploadtree2PathStr
   */
  public function test_Uploadtree2PathStr (){
    $uiPicker = new ui_picker();

    $reflection = new \ReflectionClass( get_class($uiPicker) );
    $method = $reflection->getMethod('Uploadtree2PathStr');
    $method->setAccessible(true);
    
    $result = $method->invoke($uiPicker,array(array('ufile_name'=>'path'),array('ufile_name'=>'to'),array('ufile_name'=>'nowhere')));
    assertThat($result, is('/path/to/nowhere'));
  }
  
}
