/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: J.Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

$(document).ready(function () {
  $('.supervised').bind('change', function () {
    $('#changedSomething').val('true');
  });
});