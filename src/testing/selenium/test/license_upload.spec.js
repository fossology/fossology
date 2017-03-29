/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");
let UploadVisibility = require("./helper/Upload/UploadVisibility");
let LicenseDeciderSettings = require("./helper/Upload/LicenseDeciderSettings");
let AgentSettings = require("./helper/AgentSettings");

describe('License Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function checkIfLicenseScansScheduled() {
    //go to element job page
    element(by.css('#dmessage a')).click();
    let wordsToBeContained = ["copyright", "ecc", "mimetype", "monk", "nomos", "pkgagent", "reuser", "decider"];
    browser.sleep(400);
    for (let i = 0; i < wordsToBeContained.length; i++) {
      let currentSearchWord = wordsToBeContained[i];
      expect(element(by.cssContainingText("tr td", currentSearchWord)).isPresent()).toBe(true);
    }
  }

  function uploadMITLicense() {
    //upload file
    let licenseDeciderSettings = new LicenseDeciderSettings(true, true, true);
    let agentSettings = new AgentSettings(true, true, true, true, true, true);
    let uploadSettings = new UploadSettings(test_helper.getLicensePath() + "MIT.txt", "MIT.txt", "MIT License Test", UploadVisibility.PUBLIC, agentSettings, licenseDeciderSettings);
    test_helper.uploadFile(uploadSettings);
    return uploadSettings;
  }

  it("License Upload Test", function () {
    let uploadSettings = uploadMITLicense();

    //check if scans where scheduled
    checkIfLicenseScansScheduled();

    //check if license is MIT
    test_helper.clickOnOptionOnBrowsePage("Licenses");
    browser.sleep(200);
    expect(element.all(by.css("tr td.left a")).first().getText()).toBe("MIT");

    //cleanup
    test_helper.openBrowseBugWorkAround();
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  it("License Bulk Scan Test", function () {
    let uploadSettings = uploadMITLicense();
    test_helper.clickOnOptionOnBrowsePage("Licenses");
    element(by.cssContainingText("button", "Bulk Recognition")).click();
    let textToPaste = 'Permission is hereby granted, free of charge, to any person obtaining a copy of this ' +
      'software and associated documentation files (the "Software"), to deal in the Software without ' +
      'restriction, including without limitation the rights to use, copy, modify, merge, publish, ' +
      'distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom';
    element(by.name("bulkRefText")).sendKeys(textToPaste);
    element(by.id("bulkFormAddLicense")).click();
    let button = element(by.cssContainingText("button", "Schedule Bulk scan"));
    test_helper.waitForElementToBePresent(button);
    button.click();
    browser.sleep(200);
    expect(element(by.name("bulkIdResult")).getText()).toContain("scan scheduled as job #");
    test_helper.deleteFile(uploadSettings.uploadName);
  });

  function checkLicenseOneShot(filename, licenseToFind) {
    browser.get(test_helper.getURL("?mod=agent_nomos_once"));
    let uploadSettings = test_helper.getDefaultLicenseUploadSettings(filename);
    element(by.name("file_input")).sendKeys(uploadSettings.path);
    element(by.css("input[value='Analyze']")).click();
    browser.sleep(200);
    test_helper.checkDMessage(uploadSettings.uploadName + ": " + licenseToFind);
  }

  function checkCopyrightOneShot(filename, copyrightCount, urlCount) {
    browser.get(test_helper.getURL("?mod=agent_copyright_once"));
    element(by.name("licfile")).sendKeys(test_helper.getDefaultLicenseUploadSettings(filename).path);
    element(by.css("input[value='Upload and scan']")).click();
    browser.sleep(200);
    expect(element.all(by.css("span.hi-cp")).count()).toBe(copyrightCount);
    expect(element.all(by.css("span.hi-url")).count()).toBe(urlCount);
  }

  function checkMonkOneShot(filename, licenseToFind) {
    browser.get(test_helper.getURL("?mod=oneshot-monk"));
    element(by.name("file_input")).sendKeys(test_helper.getDefaultLicenseUploadSettings(filename).path);
    element(by.css("input[value='Analyze']")).click();
    browser.sleep(200);
    expect(element(by.cssContainingText("ul li a", licenseToFind)).isPresent()).toBe(true);
  }

  it("One-Shot License Analysis Test", function () {
    checkLicenseOneShot("gpl-3.0.txt", "GPL-3.0");
    checkLicenseOneShot("MIT.txt", "MIT");
  });

  it("One-Shot Copyright/Email/URL Analysis Test", function () {
    checkCopyrightOneShot("gpl-3.0.txt", 17, 4)
    checkCopyrightOneShot("MIT.txt", 1, 0)
  });

  it("One-Shot Monk", function () {
    checkMonkOneShot("gpl-3.0.txt", "GPL-3.0");
    checkMonkOneShot("MIT.txt", "MIT");
  });

  afterAll(function () {
    test_helper.logout();
  });
});
