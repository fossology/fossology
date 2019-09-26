<?php
/*
 Copyright (C) 2014-2018, Siemens AG
 Author: Daniele Fognini, Andreas Würl

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

namespace Fossology\Spasht;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;

include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Spasht agent source
 * @class SpashtAgent
 * @brief The Spasht agent
 */
class SpashtAgent extends Agent
{

  /** @var UploadDao $uploadDao
     * UploadDao object
     */
    private $uploadDao;


    function __construct()
    {
        parent::__construct(SPASHT_AGENT_NAME, AGENT_VERSION, AGENT_REV);
        $this->uploadDao = $this->container->get('dao.upload');
    }

}
