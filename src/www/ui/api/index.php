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


require_once '/usr/local/share/fossology/vendor/autoload.php';
require_once "helper/RestHelper.php";
require_once "models/InfoType.php";
require_once "models/ScanOptions.php";
require_once "models/Analysis.php";
require_once "models/Info.php";
require_once "models/SearchResult.php";
require_once "models/Decider.php";
require_once "helper/DbHelper.php";
require_once "/usr/local/share/fossology/www/ui/search-helper.php";
require_once "/usr/local/share/fossology/lib/php/common.php";
require_once "/usr/local/share/fossology/www/ui/agent-add.php";

//TODO: REMOVE ERROR_DISPLAY
ini_set('display_errors', 1);
error_reporting(-1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;
use api\models\Info;
use \www\ui\api\models\InfoType;
use \www\ui\api\models\Decider;
use \www\ui\api\helper\DbHelper;
use \www\ui\api\models\ScanOptions;
use \www\ui\api\models\Analysis;
use api\models\SearchResult;

$app = new Silex\Application();
$app['debug'] = true;

const BASE_PATH = "/repo/api/v1/";


////////////////////////////UPLOADS/////////////////////

$app->GET(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();

  //checks if user has access to this functionality
  if($restHelper->hasUserAccess("username"))
  {
    //get the id from the fossology user
    if (is_numeric($id))
    {
      if($dbHelper->doesIdExist("upload","upload_pk", $id))
      {
        return new Response(json_encode($dbHelper->getUploads($restHelper->getUserId(), $id), JSON_PRETTY_PRINT));
      }
      else
      {
        $error = new Info(404, "File does not exist", InfoType::ERROR);
        return new Response($error->getJSON(), $error->getCode());
      }
    }
    else
    {
      $error = new Info(400, "Bad Request. $id is not a number!", InfoType::ERROR);
      return new Response($error->getJSON());
    }
  }
  else
  {
    $error = new Info(403, "No authorized to GET upload with id " . $id, InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

$app->PATCH(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  $restHelper = new RestHelper();

  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    if (is_integer($id))
    {
      return new Response("TODO");
      //TODO implement patch method
    }
    else
    {
      $error = new Info(400, "Bad Request. $id is not a number!", InfoType::ERROR);
      return new Response($error->getJSON());
    }
  }
  else
  {
    $error = new Info(403, "No authorized to PATCH upload with id " . $id, InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }

});

$app->PUT(BASE_PATH.'uploads/', function (Application $app, Request $request)
{

  $restHelper = new RestHelper();

  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    try
    {
      $put = array();
      parse_str(file_get_contents('php://input'), $put);
      return new Response("fdsfds");
    }
    catch (Exception $e)
    {
      $error = new Info(400, "Bad Request. Invalid Input", InfoType::ERROR);
      return new Response($error->getJSON(),$error->getCode());
    }
  }
  else
  {
    $error = new Info(403, "No authorized to PUT upload", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

$app->GET(BASE_PATH.'uploads/', function (Application $app, Request $request)
{
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();

  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    //get the id from the fossology user
    $response = json_encode($dbHelper->getUploads($restHelper->getUserId()), JSON_PRETTY_PRINT);
    return new Response($response, 200);
  }
  else
  {
    $error = new Info(403, "No authorized to GET upload", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }

});

$app->DELETE(BASE_PATH.'uploads/{id}', function (Application $app, Request $request, $id)
{
  require_once "../../../delagent/ui/delete-helper.php";
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();
  $id = intval($id);
  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    if (is_integer($id))
    {
      if($dbHelper->doesIdExist("upload","upload_pk", $id))
      {
        TryToDelete($id, $restHelper->getUserId(), $restHelper->getGroupId(), $restHelper->getUploadDao());
        $info = new Info(202, "Delete Job for file with id " . $id, InfoType::INFO);
        return new Response($info->getJSON(), $info->getCode());
      }
      else
      {
        $error = new Info(404, "Id " . $id . " doesn't exist", InfoType::ERROR);
        return new Response($error->getJSON(), $error->getCode());
      }
    }
    else
    {
      $error = new Info(400, "Bad Request. $id is not a number!", InfoType::ERROR);
      return new Response($error->getJSON());
    }
  }
  else
  {
    $error = new Info(403, "Not authorized to PUT upload", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

////////////////////////////SEARCH/////////////////////

$app->GET(BASE_PATH.'search/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper();

  //check user access to search
  if($restHelper->hasUserAccess("SIMPLE_KEY"))
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
      return new Response($error->getJSON(), $error->getCode());
    }

    /**
     * check if filesizeMin && filesizeMax are numeric, if existing
     */
    if ((isset($filesizeMin) && !is_numeric($filesizeMin)) || (isset($filesizeMax) && !is_numeric($filesizeMax)))
    {
      $error = new Info(400, "Bad Request. filesizemin and filesizemax need to be numeric",
        InfoType::ERROR);
      return new Response($error->getJSON(), $error->getCode());
    }

    $restHelper = new RestHelper();
    $dbHelper = new DbHelper();

    $item = GetParm("item", PARM_INTEGER);
    $results = GetResults($item, $filename, $tag, 0, $filesizeMin, $filesizeMax, $searchType,
      $license, $copyright, $restHelper->getUploadDao(), $restHelper->getGroupId(), $dbHelper->getPGCONN());

    $searchResults = [];
    //rewrite it and add additional information about it's parent upload
    for($i=0; $i < sizeof($results); $i++)
    {
      $currentUpload = $dbHelper->getUploads($restHelper->getUserId(), $results[$i]["upload_fk"])[0];
      $uploadTreePk = $results[$i]["uploadtree_pk"];
      $filename = $dbHelper->getFilenameFromUploadTree($uploadTreePk);
      $currentResult = new SearchResult($currentUpload, $uploadTreePk, $filename);
      $searchResults[] = $currentResult->getJSON();
    }
    return new Response(json_encode($searchResults, JSON_PRETTY_PRINT));
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(401, "Not authorized to search", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

////////////////////////////ADMIN-USERS/////////////////////

$app->GET(BASE_PATH.'users/', function(Application $app, Request $request)
{
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();
  //check user access to search
  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    $users = $dbHelper->getUsers();
    return new Response($users, 200);
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }


});

$app->GET(BASE_PATH.'users/{id}', function(Application $app, Request $request, $id)
{
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();
  //check user access to search
  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    if(is_numeric($id))
    {
      if($dbHelper->doesIdExist("users","user_pk", $id))
      {
        $users = $dbHelper->getUsers($id);
        return new Response($users, 200);
      }
      else
      {
        $error = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return new Response($error->getJSON(), $error->getCode());
      }

    }
    else
    {
      $error = new Info(400, "Bad request. $id is not a number!", InfoType::ERROR);
      return new Response($error->getJSON(), $error->getCode());
    }
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }


});

$app->DELETE(BASE_PATH.'users/{id}', function(Application $app, Request $request, $id)
{
  $restHelper = new RestHelper();
  $dbHelper = new DbHelper();
  //check user access to search
  if($restHelper->hasUserAccess("SIMPLE_KEY"))
  {
    if(is_numeric($id))
    {
      if($dbHelper->doesIdExist("users","user_pk", $id))
      {
        $dbHelper->deleteUser($id);
        $info = new Info(202, "User will be deleted", InfoType::INFO);
        return new Response($info->getJSON(), $info->getCode());
      }
      else
      {
        $error = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return new Response($error->getJSON(), $error->getCode());
      }

    }
    else
    {
      $error = new Info(400, "Bad request. $id is not a number!", InfoType::ERROR);
      return new Response($error->getJSON(), $error->getCode());
    }
  }
  else
  {
    //401 because every user can search. Only not logged in user can't
    $error = new Info(403, "Not authorized to access users", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

$app->GET(BASE_PATH.'jobs/', function(Application $app, Request $request)
{
  $dbHelper = new DbHelper();
  $limit = $request->headers->get("limit");
  if(isset($limit) && (!is_numeric($limit) || $limit < 0))
  {
    $error = new Info(400, "Limit cannot be smaller than 1 and has to be numeric!", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
  return new Response($dbHelper->getJobs($limit), 200);
});

$app->POST(BASE_PATH.'jobs/', function(Application $app, Request $request)
{
  $folder = $request->headers->get("folderId");
  $upload = $request->headers->get("uploadId");
  $scanOptionsJSON = json_decode($request->headers->get("scanOptions"));
  $analysis = new Analysis();
  $decider = new Decider();
  $reuse = $scanOptionsJSON["reuse"] ?: -1;
  $scanOptions = new ScanOptions($analysis, $reuse, $decider);

  //TODO query new job for upload with id with options
});

$app->GET(BASE_PATH.'jobs/{id}', function(Application $app, Request $request, $id)
{
  $dbHelper = new DbHelper();

  if(isset($id) && is_numeric($id))
  {
    if($dbHelper->doesIdExist("job", "job_pk", $id))
    {
      return new Response($dbHelper->getJobs(0, $id), 200);
    }
    else
    {
      $error = new Info(404, "Job id ".$id." doesn't exist", InfoType::ERROR);
      return new Response($error->getJSON(), $error->getCode());
    }
  }
  else
  {
    $error = new Info(400, "Id has to be numeric!", InfoType::ERROR);
    return new Response($error->getJSON(), $error->getCode());
  }
});

$app->run();
