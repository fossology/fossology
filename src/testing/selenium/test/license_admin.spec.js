/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");
let License = require("./helper/AdviceLicense/License");


describe('License Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function checkLicense(licenseName) {
    let licenseNameOption = element(by.css("option[value='" + licenseName + "']"));
    test_helper.waitForElementToBePresent(licenseNameOption);
    licenseNameOption.click();
    let findButton = element(by.css("input[value='Find']"));
    test_helper.waitForElementToBePresent(findButton);
    findButton.click();
    expect(by.cssContainingText("td", licenseName));
  }

  it("License Find Check", function () {
    browser.get(test_helper.getURL("?mod=admin_license"));
    let select = element(by.css("select[name='req_shortname']"));
    test_helper.waitForElementToBePresent(select);
    select.click();
    let licenseNames = ["MIT", "GPL", "Libpng", "M+", "JSON", "Adobe", "Fedora"];
    for (let i = 0; i < licenseNames.length; i++) {
      checkLicense(licenseNames[i]);
    }
  });

  afterAll(function () {
    test_helper.logout();
  });

});
