/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

let seleniumRemoteAddress = process.env.SELENIUM_ENV;

exports.config =
  {
    framework: 'jasmine',
    seleniumAddress: seleniumRemoteAddress,
    specs:
      ["upload.spec.js", "user.spec.js",
        "group.spec.js", "search.spec.js",
        "customize.spec.js", "tag.spec.js",
        "license_upload.spec.js", "license_browser.spec.js",
        "organize.spec.js", "advice_license.spec.js",
        "license_admin.spec.js", "file_permission.spec.js"],
    capabilities:
      {
        browserName: 'chrome'
      }
  }
