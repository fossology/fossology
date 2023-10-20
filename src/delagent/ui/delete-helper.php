<?php
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

include_once dirname(dirname(__DIR__)) . "/lib/php/common.php";
include_once "DeleteResponse.php";
include_once "DeleteMessages.php";
use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\DelAgent\UI\DeleteResponse;

/**
 * \brief Given a folder_pk, try to add a job after checking permissions.
 * @param $uploadpk - the upload(upload_id) you want to delete
 * @param $user_pk - the user_id
 * @param $group_pk - the group_id
 * @param $uploadDao - an instance of a uploadDao
 * @return DeleteResponse with the message.
 */
function TryToDelete($uploadpk, $user_pk, $group_pk, $uploadDao)
{
  if (! $uploadDao->isEditable($uploadpk, $group_pk)) {
    $returnMessage = DeleteMessages::NO_PERMISSION;
    return new DeleteResponse($returnMessage);
  }

  $rc = DeleteUpload(intval($uploadpk), $user_pk, $group_pk, $uploadDao);

  if (! empty($rc)) {
    $returnMessage = DeleteMessages::SCHEDULING_FAILED;
    return new DeleteResponse($returnMessage);
  }

  /* Need to refresh the screen */
  $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
  $LinkText = _("View Jobs");
  $returnMessage = DeleteMessages::SUCCESS;
  return new DeleteResponse($returnMessage,
    " <a href=$URL>$LinkText</a>");
}

/**
 * \brief Given a folder_pk, add a job.
 * @param $uploadpk - the upload(upload_id) you want to delete
 * @param $Depends - Depends is not used for now
 * @param $user_pk - Id of a user
 * @param $group_pk - Id of the group
 * @return NULL on success, string on failure.
 */
function DeleteUpload($uploadpk, $user_pk, $group_pk, $Depends = NULL)
{
  /* Prepare the job: job "Delete" */
  $jobpk = JobAddJob($user_pk, $group_pk, "Delete", $uploadpk);
  if (empty($jobpk) || ($jobpk < 0)) {
    $text = _("Failed to create job record");
    return ($text);
  }
  /* Add job: job "Delete" has jobqueue item "delagent" */
  $jqargs = "DELETE UPLOAD $uploadpk";
  $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
  if (empty($jobqueuepk)) {
    $text = _("Failed to place delete in job queue");
    return ($text);
  }

  /* Tell the scheduler to check the queue. */
  $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
  if (!$success) {
    $error_msg = _("Is the scheduler running? Your jobs have been added to job queue.");
    $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
    $LinkText = _("View Jobs");
    return "$error_msg <a href=$URL>$LinkText</a>";
  }
  return (null);
} // Delete()

/**
 * @param $uploadpks
 * @brief starts deletion and handles error messages
 * @return string
 */
function initDeletion($uploadpks)
{
  if (sizeof($uploadpks) <= 0) {
    return DisplayMessage("No uploads selected");
  }

  $V = "";
  $errorMessages = [];
  $deleteResponse = null;
  for ($i=0; $i < sizeof($uploadpks); $i++) {
    $deleteResponse = TryToDelete(intval($uploadpks[$i]));

    if ($deleteResponse->getDeleteMessageCode() != DeleteMessages::SUCCESS) {
      $errorMessages[] = $deleteResponse;
    }
  }

  if (sizeof($uploadpks) == 1) {
    $V .= DisplayMessage($deleteResponse->getDeleteMessageString().$deleteResponse->getAdditionalMessage());
  } else {
    $displayMessage = "";

    if (in_array(DeleteMessages::SCHEDULING_FAILED, $errorMessages)) {
      $displayMessage .= "<br/>Scheduling failed for " .
        array_count_values($errorMessages)[DeleteMessages::SCHEDULING_FAILED] . " uploads<br/>";
    }

    if (in_array(DeleteMessages::NO_PERMISSION, $errorMessages)) {
      $displayMessage .= "No permission to delete " .
        array_count_values($errorMessages)[DeleteMessages::NO_PERMISSION]. " uploads<br/>";
    }

    $displayMessage .= "Deletion of " .
      (sizeof($uploadpks)-sizeof($errorMessages)) . " projects queued";
    $V .= DisplayMessage($displayMessage);
  }
  return $V;
}
