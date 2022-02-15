<?php
/*
 Copyright (C) 2021 Orange Author: Piotr Pszczola <piotr.pszczola@orange.com>

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

 SPDX-License-Identifier: GPL-2.0

 */

namespace Fossology\SpdxTwo;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SpdxTwoCommaSeparatedValuesAgentPlugin
 * @brief Add multiple uploads to CSV reports including SPDX identifiers
 */
class SpdxTwoCommaSeparatedValuesAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_spdx2csv";
    $this->Title =  _("Export CSV report (SPDX)");
    $this->AgentName = "spdx2csv";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    // no AgentCheckBox
  }

  /**
   * @brief Add uploads to report
   * @param array $uploads Array of upload ids
   * @return string
   */
  public function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }
}

register_plugin(new SpdxTwoCommaSeparatedValuesAgentPlugin());
