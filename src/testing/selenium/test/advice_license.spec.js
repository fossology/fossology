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

  function createLicense(license) {
    browser.get(test_helper.getURL("?mod=advice_license"));
    element(by.cssContainingText("a", "New License")).click();
    //ShortName --> Needs to be unique
    element(by.name("shortname")).sendKeys(license.shortName);
    //Full Name
    element(by.name("fullname")).sendKeys(license.fullName);
    //Reference Text
    element(by.name("rf_text")).sendKeys(license.referenceText);
    //URL
    element(by.name("url")).sendKeys(license.url);
    //Public Notes
    element(by.name("note")).sendKeys(license.publicNotes);
    //RiskLevel
    element(by.name("risk")).click();
    element(by.css("option[value='" + license.riskLevel + "']")).click();
    //Merge Request --> true | false
    if (license.mergeRequest)
      element(by.name("marydone")).click();

    //Press save button
    element(by.css("input[value='Save']")).click();
  }

  function checkLicenseCreation(license) {
    browser.get(test_helper.getURL("?mod=advice_license"));

    /////////////////////////////REMOVE THIS CODE, ONCE DELETING LICENCES IS POSSIBLE/////////////////
    //delete licenses instead
    //show all licenses
    browser.executeScript(function () {
      let select = document.getElementsByName("licenseCandidateTbl_length")[0];
      let option = document.createElement("option");
      option.setAttribute("value", "10000");
      option.textContent = "10000";
      select.appendChild(option);
    });
    browser.sleep(300);
    //select newly added license per page limit
    element(by.css("option[value='10000']")).click();
    browser.sleep(300);

    /////////////////////////////TODO REMOVE THIS CODE, ONCE DELETING LICENCES IS POSSIBLE///////////////////////
    //get license short text element and get link from parent element
    let elementTableShortText = element.all(by.cssContainingText("tr > td", license.shortName)).first();
    let elementParent = elementTableShortText.element(by.xpath(".."));
    elementParent.element(by.tagName("a")).click();
    browser.sleep(500);
    expect(element(by.name("shortname")).getAttribute("value")).toBe(license.shortName);
    expect(element(by.name("fullname")).getAttribute("value")).toBe(license.fullName);
    expect(element(by.name("rf_text")).getAttribute("value")).toBe(license.referenceText);
    expect(element(by.name("url")).getAttribute("value")).toBe(license.url);
    expect(element(by.name("note")).getAttribute("value")).toBe(license.publicNotes);
    //risk
    expect(element(by.css("option[value='" + license.riskLevel + "']")).getAttribute("selected")).toContain(true.toString());
    browser.sleep(200);
    if (license.mergeRequest) {
      expect(element(by.name("marydone")).getAttribute("checked")).toContain(license.mergeRequest);
    }
    else {
      expect(element(by.name("marydone")).getAttribute("checked")).toEqual(null);
    }
  }

  it("License Creation Test", function () {
    let riskLevel = Math.floor(Math.random() * 6);
    let licenseName = test_helper.getRandomString();
    let mergeRequest = (Math.random() >= 0.5);
    let fossologyTestLicense = new License(licenseName, "FOSSology Testing License Full Name",
      "This license is purely for testing purposes and has no actually license text or use in the real world!",
      "https://www.fossology.org/", "Public Notes for the FOSSology Testing License are here!", riskLevel, mergeRequest);
    createLicense(fossologyTestLicense);
    test_helper.checkDMessage("Successfully updated.");
    checkLicenseCreation(fossologyTestLicense);
  });


  it("Duplicate License Creation Test", function () {
    let licenseName = test_helper.getRandomString();
    let fossologyTestLicense = new License(licenseName, "FOSSology Testing License Full Name",
      "This license is purely for testing purposes and has no actually license text or use in the real world!",
      "https://www.fossology.org/", "Public Notes for the FOSSology Testing License are here!", 5, true);
    createLicense(fossologyTestLicense);
    createLicense(fossologyTestLicense);
    test_helper.checkDMessage("shortname already in use");
  });

  afterAll(function () {
    test_helper.logout();
  });

});
