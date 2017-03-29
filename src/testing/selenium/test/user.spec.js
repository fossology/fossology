/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");
let AccessLevel = require("./helper/User/AccessLevel");
let User = require("./helper/User/User");
let AgentSettings = require("./helper/AgentSettings");
let UploadSettings = require("./helper/Upload/UploadSettings");


describe('Admin Setup Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function getUserAgentSettingsFromPage() {
    let copyright = element(by.name("Check_agent_copyright")).getAttribute("checked") ? true : false;
    let ecc = element(by.name("Check_agent_ecc")).getAttribute("checked") ? true : false;
    let mime = element(by.name("Check_agent_mimetype")).getAttribute("checked") ? true : false;
    let monk = element(by.name("Check_agent_monk")).getAttribute("checked") ? true : false;
    let nomos = element(by.name("Check_agent_nomos")).getAttribute("checked") ? true : false;
    let packages = element(by.name("Check_agent_pkgagent")).getAttribute("checked") ? true : false;
    return new AgentSettings(copyright, ecc, mime, monk, nomos, packages);
  }

  function checkUserCreatedOnEditPage(user) {
    browser.get(test_helper.getURL("?mod=user_edit"));
    //select user from dropdown
    element(by.cssContainingText("option", user.username)).click();
    expect(element(by.name("user_name")).getAttribute('value')).toEqual(user.username);
    expect(element(by.name("user_desc")).getAttribute('value')).toEqual(user.description);
    expect(element(by.name("user_email")).getAttribute('value')).toEqual(user.email);

    //check if right permission value is selected for user
    expect(element.all(by.css('option[value="' + user.access_level.toString() + '"]')).last().getAttribute("selected")).toEqual('true');
    expect(element(by.name("user_email")).getAttribute('value')).toEqual(user.email);

    //check selected agents
    expect(getUserAgentSettingsFromPage()).toEqual(user.agent_settings)
  }

  it("Create normal test user", function () {
    browser.get(test_helper.getURL("?mod=user_add"));
    //this user should be created successfully
    let agentSettings = new AgentSettings(true, true, true, true, true, true);
    let successUser = new User("testuser", "This user exists to test FOSSology functionality", test_helper.getRandomString() + "@fossology.com", AccessLevel.ADMIN, "123", "123", agentSettings);
    test_helper.createUser(successUser);
    test_helper.checkDMessage("User " + successUser.username + " added.");
    checkUserCreatedOnEditPage(successUser);
    test_helper.deleteUser("testuser");
  });

  it("Create duplicate name test user", function () {
    //this user should be created successfully
    let agentSettings = new AgentSettings(true, true, true, true, true, true);
    let successUser = new User("testuser", "This user exists to test FOSSology functionality", "testing@fossology.com", AccessLevel.ADMIN, "123", "123", agentSettings);
    test_helper.createUser(successUser);

    //second user should fail because username already exists
    let failUser = new User("testuser", "This user exists to test FOSSology functionality", "testing2@fossology.com", AccessLevel.ADMIN, "123", "123", agentSettings);
    test_helper.createUser(failUser);
    test_helper.checkDMessage("User already exists. Not added.");
    test_helper.deleteUser("testuser");
  });

  it("Create duplicate user with duplicate email address", function () {
    let agentSettings = new AgentSettings(true, true, true, true, true, true);
    //thirdUser user should fail because email already exists
    let failUser1 = new User("testuser2", "This user exists to test FOSSology functionality", "testing@fossology.org", AccessLevel.ADMIN, "123", "123", agentSettings);
    test_helper.createUser(failUser1);

    //thirdUser user should fail because email already exists
    let failUser2 = new User("testuser3", "This user exists to test FOSSology functionality", "testing@fossology.org", AccessLevel.ADMIN, "123", "123", agentSettings);
    test_helper.createUser(failUser2);
    test_helper.checkDMessage("Email address already exists. Not added.");
    test_helper.deleteUser("testuser");
  });

  function checkNoAccessPermission(url) {
    browser.get(test_helper.getURL(url));
    expect(element(by.tagName("body")).getText()).toMatch("Module unavailable or your login session timed out.");

    //verify that browse page is still accessible and user is not logged out
    browser.get(test_helper.getURL("?mod=browse"));
    expect(browser.getTitle()).toBe("Browse");
  }

  it("Page Permission Test", function () {
    let someBasicUser = new User("user_" + test_helper.getRandomString(), "This user exists to test permission functionality", test_helper.getRandomString() + "@fossology.org", AccessLevel.READ_ONLY, "123", "123");
    test_helper.createUser(someBasicUser);
    test_helper.logout();
    test_helper.login(someBasicUser);
    let pagesToCheck = ["?mod=admin_monk_revision",
      "?mod=admin_bucket_pool", "?mod=foconfig",
      "?mod=dashboard", "?mod=group_manage_users",
      "?mod=group_add", "?mod=group_del",
      "?mod=admin_license", "?mod=admin_license_candidate",
      "?mod=maintagent", "?mod=admin_scheduler",
      "?mod=admin_tag_manage", "?mod=upload_permissions",
      "?mod=user_add", "?mod=showjobs",
      "?mod=admin_tag", "?mod=user_edit",
      "?mod=user_del"];

    for (let i = 0; i < pagesToCheck.length; i++) {
      checkNoAccessPermission(pagesToCheck[i]);
    }

    test_helper.logout();
    //login as fossy again to delete user
    test_helper.login();
    test_helper.deleteUser(someBasicUser.username);
  });

  it("Wrong Password Login Test", function () {
    //logout default fossy user
    test_helper.logout();

    let someBasicUser = new User("fossy", undefined, undefined, undefined, "fossy", "fossy");
    someBasicUser.password = "wrong";
    test_helper.login(someBasicUser);
    expect(element(by.tagName("body")).getText()).toContain("The combination of user name and password was not found.");

    //log back in with normal user for other tests
    test_helper.login();
  });

  afterAll(function () {
    test_helper.logout();
  })

});
