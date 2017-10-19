/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let AccessLevel = require("./helper/User/AccessLevel");
let User = require("./helper/User/User");
let UploadSettings = require("./helper/Upload/UploadSettings");

describe('License Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  let UPLOAD_PERMISSIONS =
    {
      NONE: "0",
      READ: "1",
      WRITE: "3",
      ADMIN: "10"
    };

  function changeUploadPermission(filename, uploadPermission) {
    browser.get(test_helper.getURL("?mod=upload_permissions"));
    browser.sleep(300);
    element(by.cssContainingText("select[name='uploadselect']", filename)).click();
    element(by.css("select[name='publicpermselect'] option[value='" + uploadPermission + "'")).click();
  }


  it("User Upload Permission Test", function () {
    let uploadSettings = test_helper.getDefaultLicenseUploadSettings("MIT.txt");
    let testUser = new User(test_helper.getRandomString(), "Testuser",
      test_helper.getRandomString() + "@fossology.org", AccessLevel.READ_ONLY, "123", "123");
    test_helper.uploadFile(uploadSettings);
    test_helper.createUser(testUser);
    changeUploadPermission(uploadSettings.uploadName, UPLOAD_PERMISSIONS.NONE);
    test_helper.testUploadSuccessBrowse(uploadSettings.uploadName, true);
    test_helper.logout();
    test_helper.login(testUser);

    //file should not be visible for this user
    test_helper.testUploadSuccessBrowse(uploadSettings.uploadName, false);

    test_helper.logout();
    test_helper.login();
    test_helper.deleteFile(uploadSettings.uploadName);
    test_helper.openBrowseBugWorkAround();
    test_helper.deleteUser(testUser.username);
  });


  afterAll(function () {
    test_helper.logout();
  });


});
