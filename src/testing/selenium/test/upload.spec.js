/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let path = require('path');
let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");
let LicenseDeciderSettings = require("./helper/Upload/LicenseDeciderSettings");

describe('Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function testUploadJobCreated(uploadName) {
    //test browse page
    browser.get(test_helper.getURL("?mod=showjobs"));
    //initial sleep travis
    browser.sleep(5000);
    let ele = element.all(by.cssContainingText("a", uploadName)).first();
    browser.sleep(1000);
    browser.refresh();
    browser.wait(protractor.ExpectedConditions.visibilityOf(ele), 1000, "element not visible");
    expect(ele.getText()).toBe(uploadName);
  }

  it('Normal Upload Test', function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    test_helper.uploadFile(uploadSettings);
    test_helper.checkDMessage("The file " + uploadSettings.uploadName + " has been uploaded.");
    testUploadJobCreated(uploadSettings.uploadName);
    test_helper.testUploadSuccessBrowse(uploadSettings.uploadName);
    test_helper.deleteFile(uploadSettings.uploadName);
    test_helper.openBrowseBugWorkAround();
  });

  it('Empty Upload Test', function () {
    let uploadSettings = new UploadSettings(test_helper.getRootUploadFolder() + "empty.txt", "empty.txt");
    test_helper.uploadFile(uploadSettings);
    test_helper.checkDMessage("File is empty");
  });

  afterAll(function () {
    test_helper.logout();
  });
});
