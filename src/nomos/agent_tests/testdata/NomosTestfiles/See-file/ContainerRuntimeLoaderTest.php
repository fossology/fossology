<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Twig\RuntimeLoader\ContainerRuntimeLoader;

class Twig_Tests_ContainerRuntimeLoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @requires PHP 5.3
     */
    public function testLoad()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->once())->method('has')->with('stdClass')->willReturn(true);
        $container->expects($this->once())->method('get')->with('stdClass')->willReturn(new \stdClass());

        $loader = new ContainerRuntimeLoader($container);

        $this->assertInstanceOf('stdClass', $loader->load('stdClass'));
    }

    /**
     * @requires PHP 5.3
     */
    public function testLoadUnknownRuntimeReturnsNull()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->once())->method('has')->with('Foo');
        $container->expects($this->never())->method('get');

        $loader = new ContainerRuntimeLoader($container);
        $this->assertNull($loader->load('Foo'));
    }
}
