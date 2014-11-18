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

namespace Fossology\Lib\Plugin;


use Exception;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Mockery as M;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class TestPlugin extends DefaultPlugin
{

  private $response;

  public function __construct($title, $parameters = array())
  {
    parent::__construct($title, $parameters);

    $this->response = Response::create();
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    return $this->response;
  }
}

class DefaultPluginTest extends \PHPUnit_Framework_TestCase
{
  private $name = "<name>";

  /** @var Logger|M\MockInterface */
  private $logger;

  /** @var \Twig_Environment */
  private $twigEnvironment;

  /** @var Container|M\MockInterface */
  private $container;

  /** @var TestPlugin */
  private $plugin;

  public function setUp()
  {
    global $container;
    $container = M::mock('Container');

    $this->twigEnvironment = M::mock('\Twig_Environment');
    $this->logger = M::mock('Logger');

    $container->shouldReceive('get')->with('twig.environment')->andReturn($this->twigEnvironment);
    $container->shouldReceive('get')->with('logger')->andReturn($this->logger);
    $this->container = $container;
    $GLOBAL['container'] = $container;

    $this->plugin = new TestPlugin($this->name);
  }

  public function testGetName()
  {
    assertThat($this->plugin->getName(), is($this->name));
  }

  public function testGetTitle()
  {
    assertThat($this->plugin->getTitle(), is(nullValue()));

    $title = "<title>";
    $this->plugin = new TestPlugin($this->name, array(TestPlugin::TITLE => $title));

    assertThat($this->plugin->getTitle(), is($title));
  }

  public function testGetPermission()
  {
    assertThat($this->plugin->getDBaccess(), is(TestPlugin::PERM_NONE));

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::PERMISSION => TestPlugin::PERM_WRITE));

    assertThat($this->plugin->getDBaccess(), is(TestPlugin::PERM_WRITE));
  }

  public function testIsRequiresLogin()
  {
    $this->assertTrue($this->plugin->isRequiresLogin());

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::REQUIRES_LOGIN => false));

    $this->assertFalse($this->plugin->isRequiresLogin());
  }

  public function testGetPluginLevel()
  {
    assertThat($this->plugin->getPluginLevel(), is(10));

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::LEVEL => 5));

    assertThat($this->plugin->getPluginLevel(), is(5));
  }

  public function testGetDependencies()
  {
    assertThat($this->plugin->getDependency(), is(emptyArray()));

    $dependencies = array('foo', 'bar');
    $this->plugin = new TestPlugin($this->name, array(TestPlugin::DEPENDENCIES => $dependencies));

    assertThat($this->plugin->getDependency(), is($dependencies));
  }

  public function testGetInitOrder()
  {
    assertThat($this->plugin->getInitOrder(), is(0));

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::INIT_ORDER => 15));

    assertThat($this->plugin->getInitOrder(), is(15));
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage not allowed without login
   */
  public function testExceptionWhenLoginIsRequired()
  {
    $this->plugin->getResponse();
  }
}
 