/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

module.exports = class LicenseDeciderSettings {
  constructor(nomosInMonk, reuseBulk, wipScannerUpdates) {
    this._nomosInMonk = nomosInMonk;
    this._reuseBulk = reuseBulk;
    this._wipScannerUpdates = wipScannerUpdates;
  }

  get nomosInMonk() {
    return this._nomosInMonk;
  }

  get reuseBulk() {
    return this._reuseBulk;
  }

  get wipScannerUpdates() {
    return this._wipScannerUpdates;
  }
}

