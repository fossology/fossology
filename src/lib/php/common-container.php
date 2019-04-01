<?php
/*
Copyright (C) 2015, Siemens AG

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

/**
 * @file
 * @brief Setup the dependency injection container for Symfony from services.xml
 */
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Fossology\Lib\Util\TimingLogger;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

$restCall = (isset($GLOBALS['apiCall']) && $GLOBALS['apiCall']);

$containerClassName = 'FossologyCachedContainer';

$cacheDir = array_key_exists('CACHEDIR', $GLOBALS) ? $GLOBALS['CACHEDIR'] : null;
$cacheFile = "$cacheDir/container.php";

$containerBuilder = "Symfony\Component\DependencyInjection\ContainerBuilder";

$startTime = microtime(true);

if ($restCall) {
  // Call from REST api
  $containerClassName = 'FossologyRestCachedContainer';
  $cacheFile = "$cacheDir/rest-container.php";
  $containerBuilder = "\Flexsounds\Component\SymfonyContainerSlimBridge\ContainerBuilder";
}

$cached = $cacheDir && file_exists($cacheFile);

if ($cached) {
  require_once ($cacheFile);
  $container = new $containerClassName();
} else {
  $container = new $containerBuilder();

  $container->setParameter('application_root', dirname(dirname(__DIR__)));

  $loader = new XmlFileLoader($container, new FileLocator(__DIR__));
  $loader->load('services.xml');

  if ($restCall) {
    // Set error handlers for Slim
    $container->set('notFoundHandler',
      function ($request, $response){
        $error = new Info(404, "Resource not found", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      });
    $container->set('notAllowedHandler',
      function ($request, $response, $methods){
        $error = new Info(405,
          'Method must be one of: ' . implode(', ', $methods), InfoType::ERROR);
        return $response->withHeader('Allow', implode(', ', $methods))
          ->withJson($error->getArray(), $error->getCode());
      });
    $container->set('phpErrorHandler',
      function ($request, $response, $error){
        $GLOBALS['container']->get('logger')
          ->error($error);
        $error = new Info(500, "Something went wrong! Please try again later.",
          InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      });
    $phpErrorHandler = $container->get('phpErrorHandler');
    $container->set('errorHandler', $phpErrorHandler);
  }

  $container->compile();

  if ($cacheDir && is_dir($cacheDir)) {
    $dumper = new PhpDumper($container);
    umask(0027);
    file_put_contents($cacheFile,
      $dumper->dump(array(
        'class' => $containerClassName
      )));
  }
}

if ($restCall) {
  // Replace cached values with current values
  $container->get('environment')->replace($_SERVER);
}

$GLOBALS['container'] = $container;
$logger = $container->get('logger');
$logger->pushHandler(
  new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::INFO));
$logger->pushHandler(new BrowserConsoleHandler(Logger::DEBUG));

$timeZone = $container->getParameter('time.zone');
if (! empty($timeZone)) {
  $twig = $container->get('twig.environment');
  $twig->getExtension('core')->setTimezone($timeZone);
}

/** @var TimingLogger $timingLogger */
$timingLogger = $container->get("log.timing");
$timingLogger->logWithStartTime(
  sprintf("DI container setup (cached: %s)", $cached ? 'yes' : 'no'), $startTime);
