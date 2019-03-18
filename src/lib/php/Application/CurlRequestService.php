<?php
/*
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

namespace Fossology\Lib\Application;

/**
 * @file
 * @brief Service to create new curl request handler.
 */

/**
 * @class CurlRequestService
 * Service to create new curl request handler.
 */
class CurlRequestService
{
  /**
   * Create and return a new curl request handler.
   * @param string $url URL to access.
   * @return Fossology::Lib::Application::CurlRequest
   */
  public function create($url)
  {
    return new CurlRequest($url);
  }
}
