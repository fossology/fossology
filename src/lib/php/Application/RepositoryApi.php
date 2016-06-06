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

class RepositoryApi {
  /**
   * @param string $apiRequest
   * @return array
   */
  private function curlGet($apiRequest)
  {
    $url = 'https://api.github.com/repos/fossology/fossology/'. $apiRequest;
    $handle = curl_init();
    $curlopt = array(
        CURLOPT_URL => $url,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('User-Agent: fossology')
        );
    curl_setopt_array($handle, $curlopt);

    $response = curl_exec($handle);
    if ($response !== false) {
      $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
      $resultBody = json_decode(substr($response, $headerSize), true);
    } else {
      $resultBody = array();
    }
    curl_close($handle);
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
