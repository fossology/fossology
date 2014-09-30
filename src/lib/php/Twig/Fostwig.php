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

namespace Fossology\Lib\Twig;

class Fostwig extends \Twig_Environment
{
  function __construct($loader,
          $options=array('autoescape'=>false,
                         'cache'=>'/tmp/twigcache',
                         'twig.loader.source_path'=>'../../www/ui/template')
      )
  {
    if (array_key_exists('twig.loader.source_path', $options) && substr($options['twig.loader.source_path'], 0, 1) !== '/')
    {
      $options['twig.loader.source_path'] = dirname(__DIR__) . '/' . $options['twig.loader.source_path'];
    }
    $loader->setPaths($options['twig.loader.source_path']);
    parent::__construct($loader, $options);
    $this->addFilter(new \Twig_SimpleFilter('t', 'gettext'));
  }
}