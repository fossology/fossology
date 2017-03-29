/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

module.exports = class UploadSettings {
  constructor(path, uploadName, description, uploadVisibility, agentSettings, licenseDeciderSettings, reuseSettings) {
    this._path = path;
    this._uploadName = uploadName;
    this._description = description;
    this._uploadVisibility = uploadVisibility;
    this._agentSettings = agentSettings;
    this._licenseDeciderSettings = licenseDeciderSettings;
    this._reuseSettings = reuseSettings;
  }

  get path() {
    return this._path;
  }

  set path(value) {
    this._path = value;
  }

  get uploadName() {
    return this._uploadName;
  }

  set uploadName(value) {
    this._uploadName = value;
  }

  get description() {
    return this._description;
  }

  set description(value) {
    this._description = value;
  }

  get uploadVisibility() {
    return this._uploadVisibility;
  }

  set uploadVisibility(value) {
    this._uploadVisibility = value;
  }

  get agentSettings() {
    return this._agentSettings;
  }

  set agentSettings(value) {
    this._agentSettings = value;
  }

  get licenseDeciderSettings() {
    return this._licenseDeciderSettings;
  }

  set licenseDeciderSettings(value) {
    this._licenseDeciderSettings = value;
  }

  get reuseSettings() {
    return this._reuseSettings;
  }

  set reuseSettings(value) {
    this._reuseSettings = value;
  }
}
