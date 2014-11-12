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


  public function __construct($name, $version="1.0")
  {
    if ($name === null || $name === "") {
      throw new \Exception("plugin requires a name");
    }

    $this->register();

    global $container;
    $this->container = $container;
    $this->renderer = $this->container->get('twig.environment');
  }

  private function register() {
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
  public function getObject($name) {
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

  public function initialize() {
  }

  public function execute() {
    $response = $this->getResponse();

    $response->send();
  }

}