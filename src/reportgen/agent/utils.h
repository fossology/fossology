/*
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#ifndef XML
#define XML

#include <mxml.h>
#include <glib.h>

#define TABLELOOK "04A0"

mxml_node_t* createbodyheader(mxml_node_t* doc);

void addheading(mxml_node_t* body, char* Headingname);

int ziptargetdir(char* targetdir);
int createdocxdirstructure(char* targetdir);
mxml_node_t* createcorexml(mxml_node_t* head);
mxml_node_t* createappxml(mxml_node_t* head);
mxml_node_t* createrelxml(mxml_node_t* relationships);
mxml_node_t* createcontent(mxml_node_t* content);
mxml_node_t* createnum(mxml_node_t* fnum);
mxml_node_t* createstyle(mxml_node_t* fstyle);
mxml_node_t* createfont(mxml_node_t* fonth);
mxml_node_t* createreference(mxml_node_t* refh);
mxml_node_t* createfooter(mxml_node_t* fdrd);
mxml_node_t* createheader(mxml_node_t* hdrd);
void createsectionptr(mxml_node_t* body);
mxml_node_t* createbodyheader(mxml_node_t* xml);
void addheading(mxml_node_t* body, char* Headingname);
mxml_node_t* createtable(mxml_node_t* body, char* totalwidth);
void createtablegrid(mxml_node_t* tbl,char** gridwidth,  int cols);
void createcelldataproperty(mxml_node_t* tc, char* width);
void createrowdata(mxml_node_t* tr, char* cellwidth, char* celldata);
void addparaheading(mxml_node_t* p, char* italics, char* heading,  char* lvl, char* numid);
mxml_node_t*  createnumsection(mxml_node_t* body, char* lvl, char* numid);
void addparagraph(mxml_node_t* body,char* italics, char* text);
mxml_node_t* createrowproperty(mxml_node_t* tbl);

#endif
