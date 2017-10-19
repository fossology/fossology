/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

module.exports = class User {
  constructor(username, description, email, access_level, password, password_repeat, agent_settings) {
    this._username = username;
    this._description = description;
    this._email = email;
    this._access_level = access_level;
    this._password = password;
    this._password_repeat = password_repeat;
    this._agent_settings = agent_settings;
  }

  get username() {
    return this._username;
  }

  get description() {
    return this._description;
  }

  get email() {
    return this._email;
  }

  get access_level() {
    return this._access_level;
  }

  get password() {
    return this._password;
  }

  set password(value) {
    this._password = value;
  }

  get password_repeat() {
    return this._password_repeat;
  }

  get agent_settings() {
    return this._agent_settings;
  }
}
