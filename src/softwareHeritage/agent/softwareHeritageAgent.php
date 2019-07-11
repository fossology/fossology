<?php
/*
 Copyright (C) 2019
 Author: Sandip Kumar Bhuyan<sandipbhuyan@gmail.com>

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

namespace Fossology\SoftwareHeritage;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use \GuzzleHttp\Client;

include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Software Heritage agent source
 * @class SoftwareHeritage
 * @brief The software heritage agent
 */
class softwareHeritageAgent extends Agent
{
    /** @var UploadDao $uploadDao
     * UploadDao object
     */
    private $uploadDao;


    function __construct()
    {
        parent::__construct(SOFTWARE_HERITAGE_AGENT_NAME, AGENT_VERSION, AGENT_REV);
        $this->uploadDao = $this->container->get('dao.upload');
    }

    /*
     * @brief Run software heritage for a package
     * @param $uploadId Integer
     * @see Fossology::Lib::Agent::Agent::processUploadId()
     */
    function processUploadId($uploadId)
    {
        $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
        $pfileFileDetails = $this->uploadDao->getPFileDataPerFileName($itemTreeBounds);
        foreach($pfileFileDetails as $pfileDetail)
        {
            $licenseDetails = $this->getSoftwareHeritageLicense($pfileDetail['sha256']);
            $this->insertSoftwareHeritageRecord($pfileDetail['pfile_pk'],$licenseDetails);
            $this->heartbeat(1);
        }

        return true;
    }

    /*
     * @brief Get the license details from software heritage
     * @param $sha256 String
     *
     * @return array
     */
    protected function getSoftwareHeritageLicense($sha256)
    {
        $client = new Client(['http_errors' => false]);
        $response = $client->get('https://archive.softwareheritage.org/api/1/content/sha256:'.$sha256.'/license/');
        $statusCode = $response->getStatusCode();
        if(200 === $statusCode)
        {
            $responseContent = json_decode($response->getBody()->getContents(),true);
            $licenseRecord = $responseContent["facts"][0]["licenses"];

            return $licenseRecord;
        }
        else
        {
            return ["No License Found"];
        }
    }

    /*
     * @brief Insert the License Details in softwareHeritage table
     * @param $pfileId  Integer
     * @param $license  Array
     *
     * @return boolean True if finished
     */
    protected function insertSoftwareHeritageRecord($pfileId,$licenses)
    {
        foreach($licenses as $license)
        {
            $this->uploadDao->setshDetails($pfileId, $license);
        }

        return true;
    }
}
