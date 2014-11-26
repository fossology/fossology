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

namespace Fossology\Lib\Plugin;

use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;

abstract class DefaultPlugin implements Plugin
{
  const PERMISSION = "permission";
  const REQUIRES_LOGIN = "requiresLogin";
  const ENABLE_MENU = "ENABLE_MENU";
  const LEVEL = "level";
  const DEPENDENCIES = "dependencies";
  const INIT_ORDER = "initOrder";
  const MENU_LIST = "menuList";
  const MENU_ORDER = "menuOrder";
  const MENU_TARGET = "menuTarget";
  const TITLE = "title";

  /**
   * Permissions
   * See http://www.fossology.org/projects/fossology/wiki/PermsPt2
   */
  const PERM_NONE = 0;
  const PERM_READ = 1;
  const PERM_WRITE = 3;        /* DB writes permitted */
  const PERM_ADMIN = 10;

  /** @var ContainerBuilder */
  protected $container;

  /** @var Twig_Environment */
  protected $renderer;

  /** @var Logger */
  private $logger;

  /** @var string */
  private $name;

  /** @var string */
  private $version = "1.0";

  /** @var string */
  private $title;

  /** @var int */
  private $permission = self::PERM_NONE;

  /** @var int */
  private $requiresLogin = true;

  /** @var int */
  private $PluginLevel = 10;

  /** @var array */
  private $dependencies = array();
  private $InitOrder = 0;

  private $MenuList = NULL;
  private $MenuOrder = 0;
  private $MenuTarget = NULL;

  public function __construct($name, $parameters = array())
  {
    if ($name === null || $name === "")
    {
      throw new \InvalidArgumentException("plugin requires a name");
    }
    $this->name = $name;
    foreach ($parameters as $key => $value)
    {
      $this->setParameter($key,$value);
    }

    global $container;
    $this->container = $container;
    $this->renderer = $this->getObject('twig.environment');
    $this->logger = $this->getObject('logger');
  }

  private function setParameter($key, $value)
  {
    switch ($key)
    {
      case self::TITLE:
        $this->title = $value;
        break;

      case self::PERMISSION:
        $this->permission = $value;
        break;

      case self::REQUIRES_LOGIN:
        $this->requiresLogin = $value;
        break;

      case self::LEVEL:
        $this->PluginLevel = $value;
        break;

      case self::DEPENDENCIES:
        $this->dependencies = $value;
        break;

      case self::INIT_ORDER:
        $this->InitOrder = $value;
        break;

      case self::MENU_LIST:
        $this->MenuList = $value;
        break;

      case self::MENU_ORDER:
        $this->MenuOrder = $value;
        break;

      case self::MENU_TARGET:
        $this->MenuTarget = $value;
        break;

      default:
        throw new \Exception("unhandled parameter $key in module " . $this->name);
    }
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getVersion()
  {
    return $this->version;
  }

  /**
   * @return string
   */
  public function getTitle()
  {
    return $this->title;
  }

  /**
   * @return int
   */
  public function isRequiresLogin()
  {
    return $this->requiresLogin;
  }

  /**
   * @return array
   */
  public function getDependency()
  {
    return $this->dependencies;
  }

  /**
   * @return int
   */
  public function getPluginLevel()
  {
    return $this->PluginLevel;
  }

  /**
   * @return int
   */
  public function getDBaccess()
  {
    return $this->permission;
  }

  /**
   * @return int
   */
  public function getState()
  {
    return PLUGIN_STATE_READY;
  }

  /**
   * @return int
   */
  public function getInitOrder()
  {
    return $this->InitOrder;
  }


  public function getNoMenu()
  {
    return 0;
  }

  /**
   * \brief Customize submenus.
   */
  protected function RegisterMenus()
  {
    if (isset($this->MenuList) && (!$this->requiresLogin || !empty($_SESSION['User']) && $_SESSION['User']!='Default User'))
    {
      menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->name, $this->name);
    }
  }

  /**
   * @return Response
   */
  public function getResponse()
  {
    $request = Request::createFromGlobals();

    $this->checkPrerequisites();

    $startTime = microtime(true);
    $response = $this->handle($request);
    $this->logger->debug(sprintf("handle request in %.3fs", microtime(true) - $startTime));
    return $response;
  }

  /**
   * @param $name
   * @return object
   */
  public function getObject($name)
  {
    return $this->container->get($name);
  }

  public function preInstall()
  {
    $this->RegisterMenus();
  }

  public function postInstall()
  {
  }

  public function unInstall()
  {
  }

  public function execute()
  {
    $startTime = microtime(true);

    $response = $this->getResponse();

    $this->logger->debug(sprintf("prepare response in %.3fs", microtime(true) - $startTime));

    $response->send();
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected abstract function handle(Request $request);

  /**
   * @param string $templateName
   * @param array $vars
   * @param string[] $headers
   * @return Response
   */
  protected function render($templateName, $vars = null, $headers = null)
  {
    $startTime = microtime(true);

    $content = $this->renderer->loadTemplate($templateName)
        ->render($vars ?: $this->getDefaultVars());

    $this->logger->debug(sprintf("%s: render response in %.3fs", get_class($this), microtime(true) - $startTime));
    return new Response(
        $content,
        Response::HTTP_OK,
        $headers ?: $this->getDefaultHeaders()
    );
  }

  private function checkPrerequisites()
  {
    if ($this->requiresLogin && (empty($_SESSION['User']) || $_SESSION['User']=='Default User'))
    {
      throw new \Exception("not allowed without login");
    }

    foreach ($this->dependencies as $dependency)
    {
      $id = plugin_find_id($dependency);
      if ($id < 0)
      {
        $this->unInstall();
        throw new \Exception("unsatisfied dependency '$dependency' in module '" . $this->getName() . "'");
      }
    }
  }

  /**
   * @return array
   */
  protected function getDefaultHeaders()
  {
    return array(
        'Content-type' => 'text/html',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
  }

  /**
   * @return array
   */
  protected function getDefaultVars()
  {
    $vars = array();

    global $Plugins;
    $menu = ($this->name != "menus") ? $Plugins[plugin_find_id("menus")] : null;

    $metadata = "<meta name='description' content='The study of Open Source'>\n";
    $metadata .= "<meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>\n";

    $vars['metadata'] = $metadata;

    if (!empty($this->title))
    {
      $vars[self::TITLE] = htmlentities($this->title);
    }

    $styles = "<link rel='stylesheet' href='css/fossology.css'>\n";
    $styles .= "<link rel='stylesheet' href='css/jquery.dataTables.css'>\n";
    $styles .= "<link rel='icon' type='image/x-icon' href='favicon.ico'>\n";
    $styles .= "<link rel='shortcut icon' type='image/x-icon' href='favicon.ico'>\n";

    if (!empty($menu))
    {
      $styles .= $menu->OutputCSS();
    }

    $vars['styles'] = $styles;

    if (!empty($menu))
    {
      $vars['menu'] = $menu->Output($this->title);
    }

    global $SysConf;
    $vars['versionInfo'] = array(
        'version' => $SysConf['BUILD']['VERSION'],
        'buildDate' => $SysConf['BUILD']['BUILD_DATE'],
        'commitHash' => $SysConf['BUILD']['COMMIT_HASH'],
        'commitDate' => $SysConf['BUILD']['COMMIT_DATE']
    );

    return $vars;
  }

  protected function mergeWithDefault($vars)
  {
    return array_merge($this->getDefaultVars(), $vars);
  }

  /**
   * @param string $name
   * @throws \Exception
   * @return string|null
   */
  public function __get($name)
  {
    if (method_exists($this, ($method = 'get' . ucwords($name))))
    {
      return $this->$method();
    } else
    {
      throw new \Exception("property '$name' not found in module " . $this->name);
    }
  }

  function __toString()
  {
    return getStringRepresentation(get_object_vars($this), get_class($this));
  }


}