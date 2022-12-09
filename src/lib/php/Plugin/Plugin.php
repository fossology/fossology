<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Plugin;

interface Plugin
{
  function execute();

  function preInstall();
  function postInstall();

  function unInstall();

  /**
   * @return string
   */
  function getName();

  function __toString();
}