/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");

/*
Template file for creating new test cases
 */

describe('License Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  it("Some test", function () {
    //add test here!
  });

  afterAll(function () {
    test_helper.logout();
  });

});
