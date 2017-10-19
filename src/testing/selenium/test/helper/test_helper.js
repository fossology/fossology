/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let path = require("path");
let UploadSettings = require("./Upload/UploadSettings");

let rootURL = process.env.FOSSOLOGY_ENV;
let rootFolder = process.env.FOSSOLOGY_TEST_FOLDER;
if (rootFolder == undefined)
  rootFolder = "/home/projects/fossology/src/testing/dataFiles/TestData/";
module.exports = {

    login: function (user) {
      let username = (user == undefined) ? "fossy" : user.username;
      let password = (user == undefined) ? "fossy" : user.password;
      browser.ignoreSynchronization = true;
      browser.get(rootURL);
      element(by.name('username')).sendKeys(username);
      element(by.name('password')).sendKeys(password);
      element.all(by.css("input[value='Login']")).first().click();
    },

    logout: function () {
      browser.get(this.getURL("?mod=auth"));
    },

    getDefaultArchiveUploadSettings: function () {
      let filename = "3files.tar.bz2";
      return new UploadSettings(rootFolder + "archives/" + filename, filename);
    },

    getLicensePath: function () {
      return rootFolder + "licenses/";
    },

    getRootUploadFolder: function () {
      return rootFolder;
    },

    getDefaultLicenseUploadSettings: function (filename) {
      return new UploadSettings(this.getLicensePath() + filename, filename);
    },

    getURL: function (modPart) {
      return rootURL + modPart;
    },

    getFullPath: function (filename) {
      return rootFolder + filename;
    },

    //checks FOSSology status message
    checkDMessage: function (messageToMatch) {
      expect($$('#dmessage').getText()).toMatch(messageToMatch);
    },

    deleteFile: function (uploadName, folderName = "Software Repository") {
      browser.sleep(1000);
      browser.get(this.getURL("?mod=admin_upload_delete"));
      browser.sleep(200);
      element(by.name("folder")).click();
      element(by.cssContainingText("option", folderName));
      let uploadNameElement = element.all(by.cssContainingText("option", uploadName)).first();
      this.waitForElementToBePresent(uploadNameElement);
      uploadNameElement = element.all(by.cssContainingText("option", uploadName)).first();
      uploadNameElement.click();
      element(by.css("input[value='Delete']")).click();
      browser.sleep(5000);
    },

    openBrowseBugWorkAround: function () {
      //////////////////////////////TODO remove this, when fixed //////////////////////////////////////////////
      //File never appears in Move/Copy upload view, if mod=browse is never visited
      browser.sleep(1000);
      browser.get(this.getURL("?mod=browse"));
      browser.sleep(2000);
      browser.navigate().refresh();
      browser.navigate().refresh();
      browser.navigate().refresh();
      browser.navigate().refresh();
      browser.sleep(2000);
      browser.navigate().refresh();
      browser.navigate().refresh();
      browser.navigate().refresh();
      /////////////////////////////////////////////////////////////////////////////////////////////////////////
    },

    uploadFile: function (uploadSettings) {
      browser.get(this.getURL("?mod=upload_file"));
      element(by.name('fileInput')).sendKeys(uploadSettings.path);
      if (uploadSettings.description != undefined) {
        element(by.name('descriptionInputName')).sendKeys(uploadSettings.description);
      }
      if (uploadSettings.uploadVisibility != undefined) {
        element(by.css("input[value='" + uploadSettings.uploadVisibility + "']")).click();
      }

      if (uploadSettings.agentSettings != undefined) {
        //selected agents
        let agentSettings = uploadSettings.agentSettings;
        if (agentSettings.copyright_email_author) {
          element(by.name("Check_agent_copyright")).click();
        }
        if (agentSettings.ecc) {
          element(by.name("Check_agent_ecc")).click();
        }
        if (agentSettings.mime) {
          element(by.name("Check_agent_mimetype")).click();
        }
        if (agentSettings.monk) {
          element(by.name("Check_agent_monk")).click();
        }
        if (agentSettings.nomos) {
          element(by.name("Check_agent_nomos")).click();
        }
        if (agentSettings.packages) {
          element(by.name("Check_agent_pkgagent")).click();
        }
      }

      if (uploadSettings.licenseDeciderSettings != undefined) {
        let licenseDeciderSettings = uploadSettings.licenseDeciderSettings;
        if (licenseDeciderSettings.nomosInMonk) {
          element(by.css("input[value='nomosInMonk']")).click();
        }
        if (licenseDeciderSettings.reuseBulk) {
          element(by.css("input[value='reuseBulk']")).click();
        }
        if (licenseDeciderSettings.wipScannerUpdates) {
          element(by.css("input[value='wipScannerUpdates']")).click();
        }
      }

      element(by.css("input[value='Upload']")).click();
      browser.sleep(2000);
    },

    fillInTagPage: function (tagName) {
      element(by.name("tag_name")).sendKeys(tagName);
      element(by.name("tag_desc")).sendKeys("This testing is for testing FOSSology tag functionality");
      element(by.css("input[value='Create']")).click();
      browser.sleep(500);
    },

    /**
     * chooses element on browse page dropdown
     * @param selectName
     */
    clickOnOptionOnBrowsePage: function (selectName) {
      browser.get(this.getURL("?mod=browse"));
      browser.sleep(300);
      element.all(by.className("goto-active-option")).first().click();
      element.all(by.cssContainingText("option", selectName)).first().click();
    },

    createFolder: function (folderName, description = "") {
      browser.get(this.getURL("?mod=folder_create"));
      element(by.name('newname')).sendKeys(folderName);
      element.all(by.name('description')).last().sendKeys(description);
      element(by.css("input[value='Create']")).click();
    },

    deleteFolder: function (folderName) {
      browser.get(this.getURL("?mod=admin_folder_delete"));
      browser.sleep(200);
      element(by.name('folder')).click();
      element.all(by.cssContainingText("option", folderName)).first().click();
      element(by.css("input[value='Delete']")).click();
    },

    editFolder: function (oldFolderName, newFolderName, newFolderDescription) {
      browser.get(this.getURL("?mod=folder_properties"));

      //choose foldername from dropdown
      element(by.name("oldfolderid")).click();
      element.all(by.cssContainingText("option", oldFolderName)).first().click();
      //edit folderName
      element(by.name("newname")).clear();
      element(by.name("newname")).sendKeys(newFolderName);

      //edit FolderDescription
      element(by.name("newdesc")).clear();
      element(by.name("newdesc")).sendKeys(newFolderDescription);

      //Click edit button
      element(by.css("input[value='Edit']")).click();
    },

    getRandomString: function () {
      return Math.random().toString(36).substring(7);
    },

    waitForElementToBePresent: function (element) {
      var until = protractor.ExpectedConditions;
      browser.wait(until.presenceOf(element), 5000, 'Element taking too long to appear in the DOM');
    },

    createUser: function (user) {
      browser.get(this.getURL("?mod=user_add"));
      //do actions in browser
      element.all(by.name('username')).first().sendKeys(user.username);
      if (user.description != undefined) element.all(by.name('description')).last().sendKeys(user.description);
      if (user.email != undefined) element(by.name('email')).sendKeys(user.email);

      if (user.access_level != undefined) element.all(by.css('option[value="' + user.access_level.toString() + '"]')).first().click()
      if (user.password != undefined) element(by.name('pass1')).sendKeys(user.password);
      if (user.password_repeat != undefined) element(by.name('pass2')).sendKeys(user.password_repeat);

      //selected agents
      let agentSettings = user.agent_settings;
      if (agentSettings != undefined) {
        if (agentSettings.copyright_email_author) {
          element(by.name("Check_agent_copyright")).click();
        }
        if (agentSettings.ecc) {
          element(by.name("Check_agent_ecc")).click();
        }
        if (agentSettings.mime) {
          element(by.name("Check_agent_mimetype")).click();
        }
        if (agentSettings.monk) {
          element(by.name("Check_agent_monk")).click();
        }
        if (agentSettings.nomos) {
          element(by.name("Check_agent_nomos")).click();
        }
        if (agentSettings.packages) {
          element(by.name("Check_agent_pkgagent")).click();
        }
      }

      //submit info
      element.all(by.css("input[value='Add User']")).first().click();
      browser.sleep(300);
    },

    deleteUser: function (username) {
      browser.get(this.getURL("?mod=user_del"));
      element(by.cssContainingText("option", username)).click();
      element(by.name("confirm")).click();
      element(by.css("input[value='Delete']")).click();
      this.checkDMessage("User deleted.");
    },

    testUploadSuccessBrowse: function (uploadName, intended_value = true) {
      //test browse page
      browser.sleep(200);
      browser.get(this.getURL("?mod=browse"));
      browser.sleep(500);
      expect(element(by.cssContainingText("td", uploadName)).isPresent()).toBe(intended_value);
    }
  }
