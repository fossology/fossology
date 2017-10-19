/*
 Copyright Siemens AG, 2017
 SPDX-License-Identifier:   GPL-2.0
 */

'use strict'

let zip = new require("node-zip")();
let fs = require("fs");
let path = require("path");
let dataFolderLocation = "../../data/";

if (!fs.existsSync(dataFolderLocation)) {
  fs.mkdirSync(dataFolderLocation);
}

fs.writeFile(dataFolderLocation + "index.html", '<!DOCTYPE html> <html> <head> </head> <body> <h1>FOSSOLOGY - TESTPAGE</h1> <script src="index.js"</script> </body>');
fs.writeFile(dataFolderLocation + "index.js", 'console.log("FOSSology - Test");');
fs.writeFile(dataFolderLocation + "empty.txt", "");
zip.file(dataFolderLocation + "index.html");
zip.file(dataFolderLocation + "index.js");
let zipContent = zip.generate({base64: false, compression: 'DEFLATE'});
fs.writeFile(dataFolderLocation + "test.zip", zipContent, 'binary');
