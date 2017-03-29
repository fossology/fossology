/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");

describe("Tag Tests", function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  it("Tag creation Test", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    let tagName = "tag_" + test_helper.getRandomString();
    browser.get(test_helper.getURL("?mod=admin_tag"));
    browser.sleep(300);
    test_helper.fillInTagPage(tagName);
    browser.sleep(100);
    expect(element(by.cssContainingText("td", tagName)).isPresent()).toBe(true);

    test_helper.checkDMessage("Create Tag Successful!")
    test_helper.uploadFile(uploadSettings);

    test_helper.openBrowseBugWorkAround();

    test_helper.clickOnOptionOnBrowsePage("Tag");
    browser.get(test_helper.getURL("?mod=admin_tag"));
    test_helper.fillInTagPage(uploadSettings.uploadName, tagName);
    browser.sleep(300);
    expect(element(by.cssContainingText("td", tagName)).isPresent()).toBe(true);
    test_helper.deleteFile(uploadSettings.uploadName);
    browser.sleep(1000);
    test_helper.openBrowseBugWorkAround();
  });

  afterAll(function () {
    test_helper.logout();
  });
});
