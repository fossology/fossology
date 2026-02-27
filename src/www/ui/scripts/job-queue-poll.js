/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

var jobPollQueue = [];
var currentTimeout = 1000;
var currentTimeoutObj = "";

function updateCheckSuccess(data) {
  var hasFinishedJobs = false;
  jobPollQueue = $.map(jobPollQueue, function (val) {
    var jqId = val.jqId;
    var callbacksuccess = val.callbacksuccess;
    var callbackfail = val.callbackfail;
    if ((data[jqId]) && (data[jqId].end_bits == 1)) {
      callbacksuccess(jqId);
      return null;
    }
    else if ((data[jqId]) && (data[jqId].end_bits > 1)) {
    if (typeof callbackfail === "function") {
      callbackfail(jqId);
      } 
    else {
      console.warn("Job failed but no failure callback provided. Job ID: ", jqId);
      }
      return null;
    }
    
    else {
      return val;
    }
  });
  if (jobPollQueue.length > 0) {
    if (currentTimeout < 10000) {
      currentTimeout += 1000;
    }
    currentTimeout += 1000;
    currentTimeoutObj = setTimeout(updateCheck, currentTimeout);
  } else {
    currentTimeoutObj = "";
  }
}

function updateCheck() {
  if (jobPollQueue.length > 0) {
    var jqIds = $.map(jobPollQueue, function (val) {
      return val.jqId;
    });
    $.ajax({
      type: "POST",
      url: "?mod=jobinfo",
      data: {"jqIds": jqIds},
      success: updateCheckSuccess,
      error: function () {
        jobPollQueue = [];
      }
    });
  }
}

function queueUpdateCheck(jqPk, callbacksucess, callbackfail) {
  jobPollQueue.push({"jqId": jqPk, "callbacksuccess": callbacksucess, "callbackfail": callbackfail});
  currentTimeout = 1000;
  if (currentTimeoutObj) {
    clearTimeout(currentTimeoutObj);
  }
  currentTimeoutObj = setTimeout(updateCheck, currentTimeout);
}


function linkToJob(jqPk) {
  return "<a href='?mod=showjobs&job=" + jqPk + "'>job #" + jqPk + "</a>";
}
