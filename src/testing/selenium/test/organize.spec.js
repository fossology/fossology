/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let UploadSettings = require("./helper/Upload/UploadSettings");

xdescribe('License Upload Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  it("Folder Creation Test", function () {
    let folderName = test_helper.getRandomString();
    let description = test_helper.getRandomString();
    test_helper.createFolder(folderName, description);
    browser.sleep(200);
    test_helper.checkDMessage("Folder " + folderName + " Created");
    test_helper.deleteFolder(folderName);
  });

  it("Folder Deletion Test", function () {
    let folderName = test_helper.getRandomString();
    let description = test_helper.getRandomString();
    test_helper.createFolder(folderName, description);
    test_helper.deleteFolder(folderName);
    test_helper.checkDMessage("Deletion of folder " + folderName + " added to job queue");
  });

  it("Duplicate Folder Creation Test", function () {
    let folderName = test_helper.getRandomString();
    let description = test_helper.getRandomString();
    test_helper.createFolder(folderName, description);
    test_helper.createFolder(folderName, description);
    test_helper.checkDMessage("Folder " + folderName + " Exists");
    test_helper.deleteFolder(folderName);
  });

  it("Edit Folder Properties Test", function () {
    let oldFolderName = test_helper.getRandomString();
    let newFolderName = test_helper.getRandomString();
    let oldFolderDescription = test_helper.getRandomString()
    let newFolderDescription = test_helper.getRandomString();

    test_helper.createFolder(oldFolderName, oldFolderDescription);
    test_helper.editFolder(oldFolderName, newFolderName, newFolderDescription);
    //check if status message is displayed
    test_helper.checkDMessage("Folder Properties changed");

    //check if values really changed
    browser.get(test_helper.getURL("?mod=folder_properties"));
    //choose foldername from dropdown
    element(by.name("oldfolderid")).click();
    element.all(by.cssContainingText("option", newFolderName)).first().click();
    expect(element(by.name("newname")).getAttribute("value")).toMatch(newFolderName);
    expect(element(by.name("newdesc")).getAttribute("value")).toMatch(newFolderDescription);

    test_helper.deleteFolder(newFolderName);
  });

  let MODE =
    {
      COPY: "Copy",
      MOVE: "Move"
    }


  function moveCopyFile(destFolderName, uploadSettings, mode) {
    test_helper.createFolder(destFolderName);
    test_helper.uploadFile(uploadSettings);

    test_helper.openBrowseBugWorkAround();

    browser.get(test_helper.getURL("?mod=content_move"));
    browser.sleep(300);

    //move file
    element(by.cssContainingText("option", uploadSettings.uploadName)).click();
    element(by.name("toFolder")).click();
    element(by.cssContainingText("select[name='toFolder'] option", destFolderName)).click();
    element(by.css("input[value='" + mode + "']")).click();

    browser.sleep(1000);
    //element should be gone from this folder in move mode
    let elementShouldExist = (mode == MODE.COPY);
    expect(element(by.cssContainingText("option", uploadSettings.uploadName)).isPresent()).toBe(elementShouldExist);

    //element should be in other folder in both modes
    element(by.cssContainingText("a", destFolderName)).click();
    browser.sleep(300);
    expect(element(by.cssContainingText("option", uploadSettings.uploadName)).isPresent()).toBe(true);

    test_helper.deleteFolder(destFolderName);
  }

  it("Move Files Test", function () {
    let destFolderName = "DestinationFolder";
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    moveCopyFile("destFolderName", uploadSettings, MODE.MOVE);
  });

  it("Copy Files Test", function () {
    let destFolderName = "DestinationFolder";
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    moveCopyFile("destFolderName", uploadSettings, MODE.COPY);

    ///////////////////////////////TODO: DELETE FILE AFTER FOLDER DELETED, ONCE ISSUE FIXED/////////////
    // //delete file, which should still be in the software repository, after folder is deleted ////////
    //// add function, when issue is fixed///////////////////////////////////////
    /*
     Example: Copy test.zip in the software repo, Create folder "AAA"
     Copy File in Folder "AAA"
     Delete Folder AAA
     Folder was deleted correctly
     But also the File "test.zip", which should still be in the Software Repository
     -->> The original file was deleted by deleting the folder, where the copy was located!
     */
  });

  function editUpload(oldName, newName, newDescription) {
    browser.get(test_helper.getURL("?mod=upload_properties"));
    element(by.cssContainingText("option", oldName)).click();
    element(by.name("newname")).clear();
    element(by.name("newname")).sendKeys(newName);
    element(by.name("newdesc")).clear();
    element(by.name("newdesc")).sendKeys(newDescription);
    element(by.css("input[value='Edit']")).click();
  }

  it("Edit Uploaded File Properties Test", function () {
    let uploadSettings = test_helper.getDefaultArchiveUploadSettings();
    let newUploadName = "upload_" + test_helper.getRandomString();
    test_helper.uploadFile(uploadSettings);
    test_helper.openBrowseBugWorkAround();
    editUpload(uploadSettings.uploadName, newUploadName, newUploadName + " Description");
    test_helper.testUploadSuccessBrowse(newUploadName, true);
    test_helper.deleteFile(newUploadName);
    test_helper.openBrowseBugWorkAround();
  });

  afterAll(function () {
    test_helper.logout();
  });

});
