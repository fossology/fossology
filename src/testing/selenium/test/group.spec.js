/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let User = require("./helper/User/User");
let AccessLevel = require("./helper/User/AccessLevel");

describe("Group Setup Tests", function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function deleteGroup(groupName) {
    browser.get(test_helper.getURL("?mod=group_delete"));
    browser.sleep(300);
    element.all(by.cssContainingText("option", groupName)).last().click();
    element(by.css("input[value='Delete']")).click();
    browser.sleep(200);
    test_helper.checkDMessage("Group " + groupName + " deleted.");
  }

  function createGroup(groupName) {
    browser.get(test_helper.getURL("?mod=group_add"));
    element(by.name("groupname")).sendKeys(groupName);
    element(by.css("input[value='Add']")).click();
  }

  it("Create new Group Test", function () {
    let groupName = "groupTest";
    createGroup(groupName);
    test_helper.checkDMessage("Group " + groupName + " added");
    deleteGroup("groupTest");
  });

  it("Create duplicate Group Test", function () {
    let groupName = "groupTest";
    createGroup(groupName);
    test_helper.checkDMessage("Group " + groupName + " added");
    createGroup(groupName);
    test_helper.checkDMessage("Group already exists. Not added.");
    deleteGroup("groupTest");
  });

  it("Manage Group Test", function () {
    let groupName = "group_" + test_helper.getRandomString();
    let userNames = [];

    createGroup(groupName);

    //create users
    for (let i = 0; i < 10; i++) {
      let currentUserName = "user_" + test_helper.getRandomString();
      let currentEmail = test_helper.getRandomString() + "@fossology.org";
      userNames.push(currentUserName);
      let user = new User(currentUserName, "Some user", currentEmail);
      test_helper.createUser(user);
    }

    browser.get(test_helper.getURL("?mod=group_manage_users"));
    element.all(by.cssContainingText("option", groupName)).first().click();
    browser.sleep(300);
    //create users
    for (let i = 0; i < userNames.length - 1; i++) {
      element.all(by.cssContainingText("select[name='userselectnew'] option", userNames[i])).last().click();
      browser.sleep(300);
    }

    //verify that users are in table
    for (let i = 0; i < userNames.length; i++) {
      expect(element(by.cssContainingText("td", userNames[i])));
    }

    //delete all users
    for (let i = 0; i < userNames.length; i++) {
      test_helper.deleteUser(userNames[i]);
    }

    //delete group
    deleteGroup(groupName);
  });

  afterAll(function () {
    test_helper.logout();
  });
});
