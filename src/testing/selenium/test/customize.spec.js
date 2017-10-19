/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let test_helper = require("./helper/test_helper");

describe('Admin Customize Tests', function () {
  beforeAll(function () {
    jasmine.DEFAULT_TIMEOUT_INTERVAL = 200000;
    test_helper.login();
  });

  function setBannerMessage(message) {
    //set banner
    browser.get(test_helper.getURL("?mod=foconfig"));
    element(by.name("new[BannerMsg]")).clear();
    element(by.name("new[BannerMsg]")).sendKeys(message);
    element(by.css("input[value='Update']")).click();
  }

  it('Banner Message Test', function () {
    let bannerText = "FOSSology Test Banner Text";
    setBannerMessage(bannerText);
    //open a few pages and check all of them. Banner should be visible
    let pagesToCheck = ["?mod=foconfig", "?mod=browse", "?mod=search", "?mod=user_edit", "?mod=user_add", "?mod=upload_vcs", "?mod=showjobs"]
    //check for banner
    for (let i = 0; i < pagesToCheck.length; i++) {
      browser.get(test_helper.getURL(pagesToCheck[i]));
      expect(element(by.cssContainingText("div", bannerText)).isPresent()).toBe(true);
    }

    //reset banner
    setBannerMessage("");
  });

  afterAll(function () {
    test_helper.logout();
  });
});
