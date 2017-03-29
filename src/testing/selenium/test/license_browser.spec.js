/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let LicenseDeciderSettings = require("./helper/Upload/LicenseDeciderSettings");
let AgentSettings = require("./helper/AgentSettings");
let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");

xdescribe('License Upload Tests', function () {
  let globalUploadSettings = null;

  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
    uploadTestFile();
  });

  function uploadTestFile() {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    globalUploadSettings = uploadSettings;
    uploadSettings.agentSettings = new AgentSettings(true, true, true, true, true, true);
    uploadSettings.licenseDeciderSettings = new LicenseDeciderSettings(true, true, true);
    test_helper.uploadFile(uploadSettings);
    browser.sleep(10000);
    test_helper.openBrowseBugWorkAround();
  }

  function openLicenseBrowseTab(tabName) {
    browser.get(test_helper.getURL("?mod=browse"));
    let browseTabLink = element(by.cssContainingText("a", globalUploadSettings.uploadName));
    test_helper.waitForElementToBePresent(browseTabLink);
    browseTabLink.click();
    let smallA = element(by.cssContainingText("small", tabName));
    test_helper.waitForElementToBePresent(smallA);
    smallA.click();
    browser.sleep(3000);
  }

  it("License Upload File Browser Test", function () {
    openLicenseBrowseTab("File Browser");
    expect(element.all(by.css("tbody[role='alert'] > tr")).count()).toBe(16);
  });

  it("License Upload Copyright Test", function () {
    openLicenseBrowseTab("Copyright/Email/URL");
    expect(element.all(by.css("tbody[role='alert'] tr")).count()).toBe(135);
  });

  it("Export Restriction Browser Test", function () {
    openLicenseBrowseTab("ECC");
    expect(element.all(by.css("tbody[role='alert'] tr")).count()).toBe(6);
  });

  it("License List Test", function () {
    openLicenseBrowseTab("License List");
    element(by.name("agentToInclude_monk")).click();
    element(by.name("agentToInclude_nomos")).click();
    element(by.name("agentToInclude_ninka")).click();
    browser.sleep(300);
    element(by.css("input[value='Generate list']")).click();
    expect(element(by.tagName("pre")).getText()).toContain("APSL-2.0");
  });

  it("File Information Test", function () {
    openLicenseBrowseTab("Info");
    browser.sleep(300);
    expect(element(by.cssContainingText("td", "application/x-bzip")).isPresent()).toBe(true);
  });

  function deleteTestFile() {
    test_helper.deleteFile(globalUploadSettings.uploadName);
  }

  afterAll(function () {
    deleteTestFile();
    test_helper.logout();
  });

});
