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
// require_once (dirname(dirname(dirname(__DIR__))).'/lib/php/common.php');
// require_once (dirname(dirname(dirname(__DIR__))).'/lib/php/Plugin/FO_Plugin.php');
    
class FO_Plugin{
  function __construct(){ defined('PLUGIN_DB_WRITE') or define('PLUGIN_DB_WRITE',3);}
  function Initialize(){}
}
require_once ( (dirname(dirname(__DIR__)).'/ui/browse-processPost.php') );


class BrowseProcessPostTest extends \PHPUnit_Framework_TestCase
{

  public function testCreateSelect()
  {
    

    
    
    $browseProcessPost = new browseProcessPost();

    $reflection = new \ReflectionClass( get_class($browseProcessPost) );
    $method = $reflection->getMethod('createSelect');
    $method->setAccessible(true);
    
    $result = $method->invoke($browseProcessPost,$id='ok',array('k1'=>'v1','k2'=>'v2'),"k2",$actions=" ");
    $inner = '<option value="k1">v1</option><option value="k2" selected>v2</option>';
    $expected = "<select name=\"$id\" id=\"$id\" $actions>$inner</select>";
    assertThat($result, is($expected));
  }

}
