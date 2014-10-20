/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Johannes Najjar
 
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
      callbackfail(jqId);
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
  return "<a href='?mod=showjobs&show=job&job=" + jqPk + "'>job #" + jqPk + "</a>";
}