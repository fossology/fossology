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

use Mockery as M;

global $container;
$container = M::mock('ContainerBuilder');
$container->shouldReceive('get');
$wwwPath = dirname(dirname(dirname(__DIR__)));
require_once($wwwPath.'/ui/ui-menus.php');
if(!function_exists('register_plugin')){ function register_plugin(){}}
require_once ( dirname(dirname(dirname(__DIR__))).'/ui/page/AdviceLicense.php' );

class AdviceLicenseTest extends \PHPUnit_Framework_TestCase
{
  public function testBool2checkbox()
  {
    $plugin = new Fossology\UI\Page\AdviceLicense();

    $reflection = new \ReflectionClass( get_class($plugin) );
    $method = $reflection->getMethod('bool2checkbox');
    $method->setAccessible(true);
    
    $resultTrue = $method->invoke($plugin,true);
    $expectedTrue = '<input type="checkbox" checked="checked" disabled="disabled"/>';
    assertThat($resultTrue, is($expectedTrue));
    
    $resultFalse = $method->invoke($plugin,false);
    $expectedFalse = '<input type="checkbox" disabled="disabled"/>';
    assertThat($resultFalse, is($expectedFalse));
  }

}
