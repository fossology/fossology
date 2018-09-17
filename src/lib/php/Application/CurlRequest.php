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

class CurlRequest
{
  private $handle = null;

  public function __construct($url)
  {
    $this->handle = curl_init($url);
  }

  public function setOptions($options)
  {
    curl_setopt_array($this->handle, $options);
  }

  public function execute()
  {
    return curl_exec($this->handle);
  }

  public function getInfo($resource)
  {
    return curl_getinfo($this->handle, $resource);
  }

  public function close()
  {
    curl_close($this->handle);
  }
}
