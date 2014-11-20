<?php

use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

$containerClassName = 'FossologyCachedContainer';

$cacheDir = array_key_exists('CACHEDIR', $GLOBALS) ? $GLOBALS['CACHEDIR'] : null;
$cacheFile = "$cacheDir/container.php";

$startTime = microtime(true);
$cached = $cacheDir && file_exists($cacheFile);

if ($cached) {
  require_once($cacheFile);
  $container = new $containerClassName();
} else {
  $container = new ContainerBuilder();

  $container->setParameter('application_root', dirname(dirname(__DIR__)));

  $loader = new XmlFileLoader($container, new FileLocator(__DIR__));
  $loader->load('services.xml');

  $container->compile();

  if ($cacheDir && is_dir($cacheDir))
  {
    $dumper = new PhpDumper($container);
    file_put_contents(
        $cacheFile,
        $dumper->dump(array('class' => $containerClassName))
    );
  }
}

$endTime = microtime(true);

$GLOBALS['container'] = $container;

$logger = $container->get('logger');
$logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::INFO));
$logger->pushHandler(new BrowserConsoleHandler(Logger::DEBUG));

$timingLogger = new \Fossology\Lib\Util\TimingLogger($logger);
$timingLogger->logWithStartAndEndTime(sprintf("DI container setup (cached: %s)", $cached ? 'yes' : 'no'), $startTime, $endTime);
