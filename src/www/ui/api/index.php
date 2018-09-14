<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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
 ***************************************************************/
/**
 * @dir
 * @brief REST api for FOSSology
 * @file
 * @brief Provides router for REST api requests
 */
namespace Fossology\UI\Api;

// setup autoloading
require_once(dirname(dirname(dirname(__DIR__))) . "/vendor/autoload.php");
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/lib/php/common.php";
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/lib/php/Plugin/FO_Plugin.php";
require_once dirname(__DIR__) . "/search-helper.php";

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Decider;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ScanOptions;
use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\SearchResult;
use Fossology\UI\Api\Models\Reuser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Silex\Application;


$app = new Application();

const BASE_PATH = "/v1/";
const AUTH_METHOD = "SIMPLE_KEY";


/* decode JSON data for API requests */
$app->before(function (Request $request) {
  if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
    $data = json_decode($request->getContent(), true);
    $request->request->replace(is_array($data) ? $data : array());
  }
});

////////////////////////////UPLOADS/////////////////////

$app->GET(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  // Checks if user has access to this functionality
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $thisSession = sessionGetter();
    // Get the id from the fossology user
    if (is_numeric($id))
    {
      if($dbHelper->doesIdExist("upload","upload_pk", $id))
      {
        return $app->json($dbHelper->getUploads($thisSession->get(Auth::USER_ID), $id), 200);
      }
      else
      {
        $error = new Info(404, "File does not exist", InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }
    }
    else
    {
      $error = new Info(400, "Bad Request. $id is not a number!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    $error = new Info(403, "No authorized to GET upload with id " . $id, InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }

});

$app->PATCH(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);

  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $newFolderID = $request->headers->get('folderId');
    $info = $restHelper->copyUpload($id, $newFolderID, false);
    return $app->json($info->getArray(), $info->getCode());
  }
  else
  {
    $error = new Info(403, "No authorized to PATCH upload with id " . $id, InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }

});

$app->PUT(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);

  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $newFolderID = $request->headers->get('folderId');
    $info = $restHelper->copyUpload($id, $newFolderID, true);
    return $app->json($info->getArray(), $info->getCode());
  }
  else
  {
    $error = new Info(403, "No authorized to PUT upload", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

$app->GET(BASE_PATH.'uploads/', function (Application $app, Request $request)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();

  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    // Get the id from the fossology user
    $response = $dbHelper->getUploads($restHelper->getUserId());
    return $app->json($response, 200);
  }
  else
  {
    $error = new Info(403, "No authorized to GET upload", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }

});

$app->DELETE(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  require_once "../../../delagent/ui/delete-helper.php";
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  $id = intval($id);
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    if (is_integer($id))
    {
      if($dbHelper->doesIdExist("upload","upload_pk", $id))
      {
        TryToDelete($id, $restHelper->getUserId(), $restHelper->getGroupId(), $restHelper->getUploadDao());
        $info = new Info(202, "Delete Job for file with id " . $id, InfoType::INFO);
        return $app->json($info->getArray(), $info->getCode());
      }
      else
      {
        $error = new Info(404, "Id " . $id . " doesn't exist", InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }
    }
    else
    {
      $error = new Info(400, "Bad Request. $id is not a number!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    $error = new Info(403, "Not authorized to PUT upload", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

////////////////////////////SEARCH/////////////////////

$app->GET(BASE_PATH.'search/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();

  //check user access to search
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $searchType = $request->headers->get("searchType");
    $filename = $request->headers->get("filename");
    $tag = $request->headers->get("tag");
    $filesizeMin = $request->headers->get("filesizemin");
    $filesizeMax = $request->headers->get("filesizemax");
    $license = $request->headers->get("license");
    $copyright = $request->headers->get("copyright");

    //set searchtype to search allfiles by default
    if (!isset($search_type))
    {
      $searchType = "allfiles";
    }

    /**
     * check if at least one parameter was given
     */
    if (!isset($filename) && !isset($tag) && !isset($filesizeMin)
      && !isset($filesizeMax) && !isset($license) && !isset($copyright))
    {
      $error = new Info(400, "Bad Request. At least one parameter, containing a value is required",
        InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }

    /**
     * check if filesizeMin && filesizeMax are numeric, if existing
     */
    if ((isset($filesizeMin) && !is_numeric($filesizeMin)) || (isset($filesizeMax) && !is_numeric($filesizeMax)))
    {
      $error = new Info(400, "Bad Request. filesizemin and filesizemax need to be numeric",
        InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }

    $item = GetParm("item", PARM_INTEGER);
    list($results, $matches) = GetResults($item, $filename, $tag, 0,
      $filesizeMin, $filesizeMax, $searchType, $license, $copyright,
      $restHelper->getUploadDao(), $restHelper->getGroupId(),
      $dbHelper->getPGCONN());

    $searchResults = [];
    //rewrite it and add additional information about it's parent upload
    for($i=0; $i < sizeof($results); $i++)
    {
      $currentUpload = $dbHelper->getUploads($restHelper->getUserId(), $results[$i]["upload_fk"])[0];
      $uploadTreePk = $results[$i]["uploadtree_pk"];
      $filename = $dbHelper->getFilenameFromUploadTree($uploadTreePk);
      $currentResult = new SearchResult($currentUpload, $uploadTreePk, $filename);
      $searchResults[] = $currentResult->getArray();
    }
    return $app->json($searchResults);
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(401, "Not authorized to search", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

////////////////////////////ADMIN-USERS/////////////////////

$app->GET(BASE_PATH.'users/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  //check user access to search
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $users = $dbHelper->getUsers();
    return $app->json($users, 200);
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }


});

$app->GET(BASE_PATH.'users/{id}', function(Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  //check user access to search
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    if(is_numeric($id))
    {
      if($dbHelper->doesIdExist("users","user_pk", $id))
      {
        $users = $dbHelper->getUsers($id);
        return $app->json($users, 200);
      }
      else
      {
        $error = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }

    }
    else
    {
      $error = new Info(400, "Bad request. $id is not a number!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }


});

$app->DELETE(BASE_PATH.'users/{id}', function(Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  //check user access to search
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    if(is_numeric($id))
    {
      if($dbHelper->doesIdExist("users","user_pk", $id))
      {
        $dbHelper->deleteUser($id);
        $info = new Info(202, "User will be deleted", InfoType::INFO);
        return $app->json($info->getArray(), $info->getCode());
      }
      else
      {
        $error = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }

    }
    else
    {
      $error = new Info(400, "Bad request. $id is not a number!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

$app->GET(BASE_PATH.'auth/', function (Application $app, Request $request)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  $username = $request->query->get("username");
  $password = $request->query->get("password");
  // Checks if user is valid
  if($restHelper->getAuthHelper()->checkUsernameAndPassword($username, $password))
  {
    $base64String = base64_encode("$username:$password");
    $newHeader = "authorization: Basic $base64String";
    // Create the response header
    return $app->json([
      "header" => $newHeader
    ]);
  }
  else
  {
    $error = new Info(404, "Username or password is incorrect", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }

});

$app->GET(BASE_PATH.'jobs/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();
  $limit = $request->headers->get("limit");
  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    if(isset($limit) && (!is_numeric($limit) || $limit < 0))
    {
      $error = new Info(400, "Limit cannot be smaller than 1 and has to be numeric!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
    return $app->json($dbHelper->getJobs($limit), 200);
  }
  else
  {
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

$app->POST(BASE_PATH.'jobs/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper($request);

  // Initialize plugins
  require_once dirname(dirname(dirname(__DIR__))) . "/lib/php/common-plugin.php";
  plugin_load();
  plugin_preinstall();
  plugin_postinstall();

  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    $folder = $request->headers->get("folderId");
    $upload = $request->headers->get("uploadId");
    if(is_numeric($folder) && is_numeric($upload)) {
      $scanOptionsJSON = $request->request->all();

      $analysis = new Analysis();
      if(array_key_exists("analysis", $scanOptionsJSON)) {
        $analysis->setUsingArray($scanOptionsJSON["analysis"]);
      }
      $decider = new Decider();
      if(array_key_exists("decider", $scanOptionsJSON)) {
        $decider->setUsingArray($scanOptionsJSON["decider"]);
      }
      $reuser = new Reuser(0, 0, false, false);
      try {
        if(array_key_exists("reuse", $scanOptionsJSON)) {
          $reuser->setUsingArray($scanOptionsJSON["reuse"]);
        }
      } catch (\UnexpectedValueException $e) {
        $error = new Info($e->getCode(), $e->getMessage(), InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }

      $scanOptions = new ScanOptions($analysis, $reuser, $decider);
      $info = $scanOptions->scheduleAgents($folder, $upload);
      return $app->json($info->getArray(), $info->getCode());
    } else {
      $error = new Info(400, "Folder id and upload id should be integers!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
})->after(function() {
  plugin_unload();
});

$app->GET(BASE_PATH.'jobs/{id}', function(Application $app, Request $request, $id)
{
  $restHelper = new RestHelper($request);
  $dbHelper = $restHelper->getDbHelper();

  if($restHelper->hasUserAccess(AUTH_METHOD))
  {
    if(isset($id) && is_numeric($id))
    {
      if($dbHelper->doesIdExist("job", "job_pk", $id))
      {
        return $app->json($dbHelper->getJobs(0, $id), 200);
      }
      else
      {
        $error = new Info(404, "Job id ".$id." doesn't exist", InfoType::ERROR);
        return $app->json($error->getArray(), $error->getCode());
      }
    }
    else
    {
      $error = new Info(400, "Id has to be numeric!", InfoType::ERROR);
      return $app->json($error->getArray(), $error->getCode());
    }
  }
  else
  {
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return $app->json($error->getArray(), $error->getCode());
  }
});

/**
 * Get current session maintained by Symfony
 * @return \Symfony\Component\HttpFoundation\Session\Session
 */
function sessionGetter()
{
  $thisSession = new Session();
  if($thisSession->isStarted()){
    $thisSession->start();
  }
  return $thisSession;
}

$app->run();
