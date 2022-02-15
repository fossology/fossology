<?php
/***********************************************************
 * Copyright (C) 2021 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

/**
 * @file
 * A temporary replacement for twig translation using Symfony translator.
 */

/**
 * @namespace Fossology::Lib::UI::TwigExtensions
 * Place to keep custom extensions for twig
 */
namespace Fossology\Lib\UI\TwigExtensions;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Symfony\Component\Translation\Translator;

/**
 * @class
 *
 * 'trans' filter for twig
 */
class TranslateFilter extends AbstractExtension
{
  public function getFilters()
  {
    return [
      new TwigFilter('trans', [$this, 'trans'])
    ];
  }

  public function trans($input)
  {
    $translator = new Translator('en');
    return $translator->trans($input);
  }
}
