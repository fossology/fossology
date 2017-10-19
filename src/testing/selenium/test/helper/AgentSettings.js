/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

module.exports = class AgentSettings {
  constructor(copyright_email_author, ecc, mime, monk, nomos, packages) {
    this._copyright_email_author = copyright_email_author;
    this._ecc = ecc;
    this._mime = mime;
    this._monk = monk;
    this._nomos = nomos;
    this._packages = packages;
  }

  get copyright_email_author() {
    return this._copyright_email_author;
  }

  get ecc() {
    return this._ecc;
  }

  get mime() {
    return this._mime;
  }

  get monk() {
    return this._monk;
  }

  get nomos() {
    return this._nomos;
  }

  get packages() {
    return this._packages;
  }
}
