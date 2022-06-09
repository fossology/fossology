<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

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
