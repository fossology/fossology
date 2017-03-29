/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");

xdescribe("Search Tests", function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function searchFor(inputWithName, keyword, intended_result, shouldBeExactMatch) {
    browser.get(test_helper.getURL("?mod=search"));
    element(by.name(inputWithName)).sendKeys(keyword);
    element(by.css("input[value='Search']")).click();
    browser.sleep(300);
    //search the last element on page
    if (shouldBeExactMatch) {
      let ele = element.all(by.css("a b")).get(1);
      expect(ele.getText()).toContain(intended_result);
    }
    //searches whole body
    else {
      expect(element(by.tagName("body")).getText()).toContain(intended_result);
    }
  }

  it("Normal filename search", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    test_helper.uploadFile(uploadSettings);
    searchFor("filename", uploadSettings.uploadName, uploadSettings.uploadName, true);
    test_helper.openBrowseBugWorkAround();
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  it("File not existing search", function () {
    searchFor("filename", "Not-existing-file.zip", "No matching files.", false);
  });

  it("Wildcard Search", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    test_helper.uploadFile(uploadSettings);
    test_helper.openBrowseBugWorkAround();
    searchFor("filename", "%" + uploadSettings.uploadName.split(".")[1], uploadSettings.uploadName, true);
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  it("Max File Size Search", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    test_helper.uploadFile(uploadSettings);
    test_helper.openBrowseBugWorkAround();
    searchFor("sizemax", "200", "No matching files.", false);
    browser.sleep(300);
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  it("Min Filesize Search", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    test_helper.uploadFile(uploadSettings);
    test_helper.openBrowseBugWorkAround();
    searchFor("sizemin", "5000000", uploadSettings.uploadName, true);
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  it("Tag search", function () {
    let tagName = "tag_" + test_helper.getRandomString();
    browser.get(test_helper.getURL("?mod=admin_tag"));
    test_helper.fillInTagPage(tagName);
  });


  it("Illegal Chars Filesize Search", function () {
    searchFor("sizemax", "Illegal Chars", "You must choose one or more search criteria", false);
  });

  afterAll(function () {
    test_helper.logout()
  });
});
