<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Plugin;

use Exception;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\UI\Component\Menu;
use Fossology\Lib\UI\Component\MicroMenu;
use Mockery as M;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class TestPlugin extends DefaultPlugin
{

  /** @var Response */
  private $response;

  /** @var Request */
  private $request;

  public function __construct($title, $parameters = array())
  {
    parent::__construct($title, $parameters);

    $this->response = new Response();
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $this->request = $request;

    return $this->response;
  }

  /**
   * @return Request
   */
  public function getTestRequest()
  {
    return $this->request;
  }

  /**
   * @return Response
   */
  public function getTestResponse()
  {
    return $this->response;
  }
}

class DefaultPluginTest extends \PHPUnit\Framework\TestCase
{
  private $name = "<name>";

  /** @var Logger|M\MockInterface */
  private $logger;

  /** @var \Twig_Environment */
  private $twigEnvironment;

  /** @var Session|M\MockInterface */
  private $session;

  /** @var Container|M\MockInterface */
  private $container;

  /** @var Menu|M\MockInterface */
  private $menu;

  /** @var MicroMenu|M\MockInterface */
  private $microMenu;

  /** @var TestPlugin */
  private $plugin;

  protected function setUp() : void
  {
    global $SysConf;
    $SysConf = [];
    $this->session = M::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');

    global $container;
    $container = M::mock('Container');

    $this->menu = M::mock(Menu::class);
    $this->twigEnvironment = M::mock('\Twig_Environment');
    $this->logger = M::mock('Monolog\Logger');

    $container->shouldReceive('get')->with('ui.component.menu')->andReturn($this->menu);
    $container->shouldReceive('get')->with('ui.component.micromenu')->andReturn($this->microMenu);
    $container->shouldReceive('get')->with('twig.environment')->andReturn($this->twigEnvironment);
    $container->shouldReceive('get')->with('logger')->andReturn($this->logger);
    $container->shouldReceive('get')->with('session')->andReturn($this->session);
    $this->container = $container;
    $GLOBALS['container'] = $container;

    $this->plugin = new TestPlugin($this->name);
  }

  protected function tearDown() : void
  {
    M::close();
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
    assertThat($this->plugin->getDBaccess(), is(Auth::PERM_NONE));

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::PERMISSION => Auth::PERM_WRITE));

    assertThat($this->plugin->getDBaccess(), is(Auth::PERM_WRITE));
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

  public function testExceptionWhenLoginIsRequired()
  {
    $this->expectException(Exception::class);
    $this->expectExceptionMessage("not allowed without login");
    $this->plugin->getResponse();
  }

  public function testSessionIsWrappedInRequest()
  {
    $this->logger->shouldReceive("debug")->once()->with(startsWith("handle request in"));

    $this->plugin = new TestPlugin($this->name, array(TestPlugin::REQUIRES_LOGIN => false));

    $this->plugin->getResponse();

    $request = $this->plugin->getTestRequest();

    assertThat($request->getSession(), is($this->session));
  }

  public function testIsLoggedIn()
  {
    global $_SESSION;
    unset($_SESSION['User']);
    assertThat($this->plugin->isLoggedIn(), is(equalTo(false)));
    $_SESSION['User'] = 'Default User';
    assertThat($this->plugin->isLoggedIn(), is(equalTo(false)));
    $_SESSION['User'] = 'resU tlaufeD';
    assertThat($this->plugin->isLoggedIn(), is(equalTo(true)));
    $this->addToAssertionCount(3);
  }
}

