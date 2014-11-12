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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;

abstract class DefaultPlugin implements Plugin
{

  const PLUGIN_STATE_FAIL = -1; // mark it as a total failure
  const PLUGIN_STATE_INVALID = 0;
  const PLUGIN_STATE_VALID = 1; // used during install
  const PLUGIN_STATE_READY = 2;

  /**
   * @var ContainerBuilder
   */
  protected $container;

  /**
   * @var Twig_Environment
   */
  private $renderer;

  /** @var string[] */
  private $defaultHeaders = array(
      'Content-type' => 'text/html',
      'Pragma' => 'no-cache',
      'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
      'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

  /** @var  string */
  private $title;

  /** @var  string */
  private $name;

  /** @var array */
  private $dependency;

  /** @var int */
  private $state;


  public function __construct($name, $title, $dependency = array())
  {
    if ($name === null || $name === "")
    {
      throw new \Exception("plugin requires a name");
    }
    $this->name = $name;
    $this->title = $title;
    $this->dependency = $dependency;
    $this->state = self::PLUGIN_STATE_VALID;

    $this->register();

    global $container;
    $this->container = $container;
    $this->renderer = $this->container->get('twig.environment');

    $this->state = self::PLUGIN_STATE_READY;
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
  public function getTitle()
  {
    return $this->title;
  }

  /**
   * @return array
   */
  public function getDependency()
  {
    return $this->dependency;
  }

  public function getState()
  {
    return $this->state;
  }


  private function register()
  {
    global $Plugins;

    array_push($Plugins, $this);
  }

  /**
   * @return Response
   */
  public function getResponse()
  {
    $request = Request::createFromGlobals();

    return $this->handleRequest($request);
  }

  /**
   * @param $name
   * @return object
   */
  public function getObject($name)
  {
    return $this->container->get($name);
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected abstract function handleRequest(Request $request);

  /**
   * @param string $templateName
   * @param array $vars
   * @param string[] $headers
   * @return Response
   */
  protected function render($templateName, $vars, $headers = array())
  {
    $headers = array_merge($this->defaultHeaders, $headers);

    return new Response(
        $this->renderer->loadTemplate($templateName)->render($vars),
        Response::HTTP_OK,
        $headers
    );
  }

  public function initialize()
  {
  }

  public function PostInitialize()
  {
  }

  public function RegisterMenus()
  {
  }

  public function execute()
  {
    $response = $this->getResponse();

    $response->send();
  }

  /**
   * @param string name
   * @return string|null
   */
  public function __get($name)
  {
    if (method_exists($this, ($method = 'get' . ucwords($name))))
    {
      return $this->$method();
    } else return null;
  }

}