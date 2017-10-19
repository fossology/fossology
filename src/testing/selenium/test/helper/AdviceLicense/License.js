/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

module.exports = class License {
  constructor(shortName, fullName, referenceText, url, publicNotes, riskLevel, mergeRequest) {
    this._shortName = shortName;
    this._fullName = fullName;
    this._referenceText = referenceText;
    this._url = url;
    this._publicNotes = publicNotes;
    this._riskLevel = riskLevel;
    this._mergeRequest = mergeRequest;
  }

  get shortName() {
    return this._shortName;
  }

  get fullName() {
    return this._fullName;
  }

  get referenceText() {
    return this._referenceText;
  }

  get url() {
    return this._url;
  }

  get publicNotes() {
    return this._publicNotes;
  }

  get riskLevel() {
    return this._riskLevel;
  }

  get mergeRequest() {
    return this._mergeRequest;
  }
}
