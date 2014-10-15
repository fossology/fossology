#ifndef JSON_DATA_RETRIVER_H
#define JSON_DATA_RETRIVER_H

/*
 *
 * should return a json in this format:
 *
 * { "licenses" : [
 *                  { "name": "Apache-2.0", "text" : "licText", "files" : [ "/a.txt", "/b.txt" ]},
 *                  { "name": "Apache-1.0", "text" : "lic3Text", "files" : [ "/c.txt" ]},
 *                ]
 * }
 */
char* getClearedLicenses();
char* getClearedCopyright();
#endif