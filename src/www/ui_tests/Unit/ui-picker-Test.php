<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Mockery as M;

$wwwPath = dirname(dirname(__DIR__));
global $container;
$container = M::mock('ContainerBuilder');
$container->shouldReceive('get');
require_once(dirname($wwwPath).'/lib/php/Plugin/FO_Plugin.php');
if(!function_exists('register_plugin')){ function register_plugin(){}}
require_once ($wwwPath.'/ui/ui-picker.php');

class ui_picker_Test extends \PHPUnit\Framework\TestCase
{
  /**
   * \brief test for Uploadtree2PathStr
   */
  public function test_Uploadtree2PathStr (){
    global $container;
    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get');
    $uiPicker = new ui_picker();

    $reflection = new \ReflectionClass( get_class($uiPicker) );
    $method = $reflection->getMethod('Uploadtree2PathStr');
    $method->setAccessible(true);
    
    $result = $method->invoke($uiPicker,array(array('ufile_name'=>'path'),array('ufile_name'=>'to'),array('ufile_name'=>'nowhere')));
    assertThat($result, is('/path/to/nowhere'));
  }
  
}
