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

class RepositoryApi
{
  // @var CurlRequestService
  private $curlRequestService = null;

  public function __construct($curlRequestService)
  {
    $this->curlRequestService = $curlRequestService;
  }

  /**
   * @param string $apiRequest
   * @return array
   */
  private function curlGet($apiRequest)
  {
    $url = 'https://api.github.com/repos/fossology/fossology/'.$apiRequest;

    $request = $this->curlRequestService->create($url);
    $curlopt = array(
      CURLOPT_HEADER         => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => array('User-Agent: fossology'),
      CURLOPT_TIMEOUT        => 2,
    );
    $request->setOptions($curlopt);
    $response = $request->execute();
    if ($response !== false) {
      $headerSize = $request->getInfo(CURLINFO_HEADER_SIZE);
      $resultBody = json_decode(substr($response, $headerSize), true);
    } else {
      $resultBody = array();
    }
    $request->close();

    return $resultBody;
  }

  /**
   * @return array
   */
  public function getLatestRelease()
  {
    return $this->curlGet('releases/latest');
  }

  /**
   * @param int $days
   * @return array
   */
  public function getCommitsOfLastDays($days = 30)
  {
    $since = '?since=' . date('Y-m-d\\TH:i:s\\Z', time() - 3600 * 24 * $days);
    return $this->curlGet('commits' . $since);
  }

}
