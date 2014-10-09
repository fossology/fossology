#include<stdio.h>
#include<stdlib.h>
#include<string.h>
#include<dirent.h>
#include<sys/stat.h>
#include<sys/types.h>
#include<sys/wait.h>
#include<unistd.h>
#include "utils.h"

#define zip "/usr/bin/zip"
#define zipcmd "zip"
#define docprops "docProps/"
#define rels "_rels/"
#define word "word/"
#define wordrels "word/_rels/"
#define mv "/bin/mv"
#define mvcmd "mv"

mxml_node_t* createcorexml(mxml_node_t* head)
{
		mxml_node_t* cp = mxmlNewElement(head, "cp:coreProperties");
		mxmlElementSetAttr(cp, "xmlns:cp", "http://schemas.openxmlformats.org/package/2006/metadata/core-properties");
		mxmlElementSetAttr(cp, "xmlns:dc", "http://purl.org/dc/elements/1.1/");
		mxmlElementSetAttr(cp, "xmlns:dcmitype", "http://purl.org/dc/dcmitype/");
		mxmlElementSetAttr(cp, "xmlns:dcterms", "http://purl.org/dc/terms/");
		mxmlElementSetAttr(cp, "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
		mxml_node_t* dcterms = mxmlNewElement(cp, "dcterms:created");
		mxmlElementSetAttr(dcterms, "xsi:type", "dcterms:W3CDTF");
		mxmlNewText(dcterms, 0, "2014-01-02T05:53:02.00Z");
		mxml_node_t* cprevision = mxmlNewElement(cp, "cp:revision");
		mxmlNewText(cprevision, 0, "0");
        return cp;
}

mxml_node_t* createappxml(mxml_node_t* head)
{
	mxml_node_t* properties = mxmlNewElement(head, "Properties");
	mxmlElementSetAttr(properties, "xmlns", "http://schemas.openxmlformats.org/officeDocument/2006/extended-properties");
	mxmlElementSetAttr(properties, "xmlns:vt", "http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes");
	mxml_node_t* totaltime = mxmlNewElement(properties, "TotalTime");
	mxmlNewText(totaltime, 0, "0");
	return properties;
}

mxml_node_t* createrelxml(mxml_node_t* relationships)
{
		mxml_node_t* rel = mxmlNewElement(relationships, "Relationships");
		mxmlElementSetAttr(rel, "xmlns","http://schemas.openxmlformats.org/package/2006/relationships");
		mxml_node_t* relationship1 = mxmlNewElement(rel, "Relationship");
		mxmlElementSetAttr(relationship1, "Id","rId1");
		mxmlElementSetAttr(relationship1, "Type","http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties");
		mxmlElementSetAttr(relationship1, "Target","docProps/core.xml");
        mxml_node_t* relationship2 = mxmlNewElement(rel, "Relationship");
	    mxmlElementSetAttr(relationship2, "Id","rId2");
	    mxmlElementSetAttr(relationship2, "Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties");
	    mxmlElementSetAttr(relationship2, "Target","docProps/app.xml");
    
	    mxml_node_t* relationship3 = mxmlNewElement(rel, "Relationship");
	    mxmlElementSetAttr(relationship3, "Id","rId3");
	    mxmlElementSetAttr(relationship3, "Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument");
	    mxmlElementSetAttr(relationship3, "Target","word/document.xml");
	    return rel;
}
 

mxml_node_t* createcontent(mxml_node_t* content)
{
		mxml_node_t* type = mxmlNewElement(content, "Types");
		mxmlElementSetAttr(type, "xmlns","http://schemas.openxmlformats.org/package/2006/content-types");
		mxml_node_t* override1 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override1,"PartName","/_rels/.rels");
		mxmlElementSetAttr(override1,"ContentType","application/vnd.openxmlformats-package.relationships+xml");
		mxml_node_t* override2 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override2,"PartName","/word/_rels/document.xml.rels");
		mxmlElementSetAttr(override2,"ContentType","application/vnd.openxmlformats-package.relationships+xml");
		mxml_node_t* override3 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override3,"PartName","/word/header1.xml");
		mxmlElementSetAttr(override3,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml");
		mxml_node_t* override4 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override4,"PartName","/word/document.xml");
		mxmlElementSetAttr(override4,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml");
		mxml_node_t* override5 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override5,"PartName","/word/numbering.xml");
		mxmlElementSetAttr(override5,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml");
		mxml_node_t* override6 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override6,"PartName","/word/footer1.xml");
		mxmlElementSetAttr(override6,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml");
		mxml_node_t* override7 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override7,"PartName","/word/styles.xml");
		mxmlElementSetAttr(override7,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml");
		mxml_node_t* override8 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override8,"PartName","/word/fontTable.xml");
		mxmlElementSetAttr(override8,"ContentType","application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml");
		mxml_node_t* override9 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override9,"PartName","/docProps/app.xml");
		mxmlElementSetAttr(override9,"ContentType","application/vnd.openxmlformats-officedocument.extended-properties+xml");
		mxml_node_t* override10 = mxmlNewElement(type, "Override");
		mxmlElementSetAttr(override10,"PartName","/docProps/core.xml");
		mxmlElementSetAttr(override10,"ContentType","application/vnd.openxmlformats-package.core-properties+xml");
		return type;
}

mxml_node_t* createnum(mxml_node_t* fnum)
{
		mxml_node_t* numbering = mxmlNewElement(fnum, "w:numbering");
		mxmlElementSetAttr(numbering, "xmlns:w","http://schemas.openxmlformats.org/wordprocessingml/2006/main");
		mxml_node_t* absnum1 = mxmlNewElement(numbering, "w:abstractNum");
		mxmlElementSetAttr(absnum1, "w:abstractNumId","1");
		mxml_node_t* lvl1 = mxmlNewElement(absnum1, "w:lvl");
		mxmlElementSetAttr(lvl1, "w:ilvl","0");
		mxml_node_t* start1 = mxmlNewElement(lvl1, "w:start");
		mxmlElementSetAttr(start1,"w:val","1");
		mxml_node_t* numFmt1 = mxmlNewElement(lvl1, "w:numFmt");
		mxmlElementSetAttr(numFmt1,"w:val","none");
		mxml_node_t* suff1 = mxmlNewElement(lvl1, "w:suff");
		mxmlElementSetAttr(suff1, "w:val","nothing");
		mxml_node_t* lvltext1 = mxmlNewElement(lvl1, "w:lvlText");
		mxmlElementSetAttr(lvltext1, "w:val","");
		mxml_node_t* lvljc1 = mxmlNewElement(lvl1, "w:lvlJc");
		mxmlElementSetAttr(lvljc1,"w:val","left");
		mxml_node_t* ppr1 = mxmlNewElement(lvl1, "w:pPr");
		mxml_node_t* tabs1 = mxmlNewElement(ppr1, "w:tabs");
		mxml_node_t* tab1 = mxmlNewElement(tabs1, "w:tab");
		mxmlElementSetAttr(tab1, "w:pos", "2232");
		mxmlElementSetAttr(tab1, "w:val","num");
		mxml_node_t* ind1 = mxmlNewElement(ppr1, "w:ind");
		mxmlElementSetAttr(ind1, "w:hanging","432");
		mxmlElementSetAttr(ind1, "w:left", "2232");

		mxml_node_t* lvl2 = mxmlNewElement(absnum1, "w:lvl");
		mxmlElementSetAttr(lvl2, "w:ilvl","1");
		mxml_node_t* start2 = mxmlNewElement(lvl2, "w:start");
		mxmlElementSetAttr(start2,"w:val","1");
		mxml_node_t* numFmt2 = mxmlNewElement(lvl2, "w:numFmt");
		mxmlElementSetAttr(numFmt2,"w:val","none");
		mxml_node_t* suff2 = mxmlNewElement(lvl2, "w:suff");
	    mxmlElementSetAttr(suff2, "w:val","nothing");
	    mxml_node_t* lvltext2 = mxmlNewElement(lvl2, "w:lvlText");
	    mxmlElementSetAttr(lvltext2, "w:val","");
	    mxml_node_t* lvljc2 = mxmlNewElement(lvl2, "w:lvlJc");
	    mxmlElementSetAttr(lvljc2,"w:val","left");
	    mxml_node_t* ppr2 = mxmlNewElement(lvl2, "w:pPr");
	    mxml_node_t* tabs2 = mxmlNewElement(ppr2, "w:tabs");
	    mxml_node_t* tab2 = mxmlNewElement(tabs2, "w:tab");
	    mxmlElementSetAttr(tab2, "w:pos", "2376");
	    mxmlElementSetAttr(tab2, "w:val","num");
	    mxml_node_t* ind2 = mxmlNewElement(ppr2, "w:ind");
	    mxmlElementSetAttr(ind2, "w:hanging","576");
	    mxmlElementSetAttr(ind2, "w:left", "2376");

	    mxml_node_t* lvl3 = mxmlNewElement(absnum1, "w:lvl");
	    mxmlElementSetAttr(lvl3, "w:ilvl","2");
	    mxml_node_t* start3 = mxmlNewElement(lvl3, "w:start");
	    mxmlElementSetAttr(start3,"w:val","1");
	    mxml_node_t* numFmt3 = mxmlNewElement(lvl3, "w:numFmt");
	    mxmlElementSetAttr(numFmt3,"w:val","none");
	    mxml_node_t* suff3 = mxmlNewElement(lvl3, "w:suff");
	    mxmlElementSetAttr(suff3, "w:val","nothing");
        mxml_node_t* lvltext3 = mxmlNewElement(lvl3, "w:lvlText");
        mxmlElementSetAttr(lvltext3, "w:val","");
        mxml_node_t* lvljc3 = mxmlNewElement(lvl3, "w:lvlJc");
        mxmlElementSetAttr(lvljc3,"w:val","left");
        mxml_node_t* ppr3 = mxmlNewElement(lvl3, "w:pPr");
        mxml_node_t* tabs3 = mxmlNewElement(ppr3, "w:tabs");
        mxml_node_t* tab3 = mxmlNewElement(tabs3, "w:tab");
        mxmlElementSetAttr(tab3, "w:pos", "2520");
        mxmlElementSetAttr(tab3, "w:val","num");
        mxml_node_t* ind3 = mxmlNewElement(ppr3, "w:ind");
        mxmlElementSetAttr(ind3, "w:hanging","720");
        mxmlElementSetAttr(ind3, "w:left", "2520");

        mxml_node_t* lvl4 = mxmlNewElement(absnum1, "w:lvl");
        mxmlElementSetAttr(lvl4, "w:ilvl","3");
        mxml_node_t* start4 = mxmlNewElement(lvl4, "w:start");
        mxmlElementSetAttr(start4,"w:val","1");
        mxml_node_t* numFmt4 = mxmlNewElement(lvl4, "w:numFmt");
        mxmlElementSetAttr(numFmt4,"w:val","none");
        mxml_node_t* suff4 = mxmlNewElement(lvl4, "w:suff");
        mxmlElementSetAttr(suff4, "w:val","nothing");
        mxml_node_t* lvltext4 = mxmlNewElement(lvl4, "w:lvlText");
        mxmlElementSetAttr(lvltext4, "w:val","");
        mxml_node_t* lvljc4 = mxmlNewElement(lvl4, "w:lvlJc");
        mxmlElementSetAttr(lvljc4,"w:val","left");
        mxml_node_t* ppr4 = mxmlNewElement(lvl4, "w:pPr");
        mxml_node_t* tabs4 = mxmlNewElement(ppr4, "w:tabs");
        mxml_node_t* tab4 = mxmlNewElement(tabs4, "w:tab");
        mxmlElementSetAttr(tab4, "w:pos", "2664");
        mxmlElementSetAttr(tab4, "w:val","num");
        mxml_node_t* ind4 = mxmlNewElement(ppr4, "w:ind");
        mxmlElementSetAttr(ind4, "w:hanging","864");
        mxmlElementSetAttr(ind4, "w:left", "2664");

        mxml_node_t* lvl5 = mxmlNewElement(absnum1, "w:lvl");
        mxmlElementSetAttr(lvl5, "w:ilvl","4");
        mxml_node_t* start5 = mxmlNewElement(lvl5, "w:start");
        mxmlElementSetAttr(start5,"w:val","1");
        mxml_node_t* numFmt5 = mxmlNewElement(lvl5, "w:numFmt");
        mxmlElementSetAttr(numFmt5,"w:val","none");
        mxml_node_t* suff5 = mxmlNewElement(lvl5, "w:suff");
        mxmlElementSetAttr(suff5, "w:val","nothing");
        mxml_node_t* lvltext5 = mxmlNewElement(lvl5, "w:lvlText");
        mxmlElementSetAttr(lvltext5, "w:val","");
        mxml_node_t* lvljc5 = mxmlNewElement(lvl5, "w:lvlJc");
        mxmlElementSetAttr(lvljc5,"w:val","left");
        mxml_node_t* ppr5 = mxmlNewElement(lvl5, "w:pPr");
        mxml_node_t* tabs5 = mxmlNewElement(ppr5, "w:tabs");
        mxml_node_t* tab5 = mxmlNewElement(tabs5, "w:tab");
        mxmlElementSetAttr(tab5, "w:pos", "2808");
        mxmlElementSetAttr(tab5, "w:val","num");
        mxml_node_t* ind5 = mxmlNewElement(ppr5, "w:ind");
        mxmlElementSetAttr(ind5, "w:hanging","1008");
        mxmlElementSetAttr(ind5, "w:left", "2808");


        mxml_node_t* lvl6 = mxmlNewElement(absnum1, "w:lvl");
        mxmlElementSetAttr(lvl6, "w:ilvl","5");
        mxml_node_t* start6 = mxmlNewElement(lvl6, "w:start");
        mxmlElementSetAttr(start6,"w:val","1");
        mxml_node_t* numFmt6 = mxmlNewElement(lvl6, "w:numFmt");
        mxmlElementSetAttr(numFmt6,"w:val","none");
        mxml_node_t* suff6 = mxmlNewElement(lvl6, "w:suff");
        mxmlElementSetAttr(suff6, "w:val","nothing");
        mxml_node_t* lvltext6 = mxmlNewElement(lvl6, "w:lvlText");
        mxmlElementSetAttr(lvltext6, "w:val","");
        mxml_node_t* lvljc6 = mxmlNewElement(lvl6, "w:lvlJc");
        mxmlElementSetAttr(lvljc6,"w:val","left");
        mxml_node_t* ppr6 = mxmlNewElement(lvl6, "w:pPr");
        mxml_node_t* tabs6 = mxmlNewElement(ppr6, "w:tabs");
        mxml_node_t* tab6 = mxmlNewElement(tabs6, "w:tab");
        mxmlElementSetAttr(tab6, "w:pos", "2952");
        mxmlElementSetAttr(tab6, "w:val","num");
        mxml_node_t* ind6 = mxmlNewElement(ppr6, "w:ind");
        mxmlElementSetAttr(ind6, "w:hanging","1152");
        mxmlElementSetAttr(ind6, "w:left", "2952");

        mxml_node_t* lvl7 = mxmlNewElement(absnum1, "w:lvl");
        mxmlElementSetAttr(lvl7, "w:ilvl","6");
        mxml_node_t* start7 = mxmlNewElement(lvl7, "w:start");
        mxmlElementSetAttr(start7,"w:val","1");
        mxml_node_t* numFmt7 = mxmlNewElement(lvl7, "w:numFmt");
        mxmlElementSetAttr(numFmt7,"w:val","none");
        mxml_node_t* suff7 = mxmlNewElement(lvl7, "w:suff");
        mxmlElementSetAttr(suff7, "w:val","nothing");
        mxml_node_t* lvltext7 = mxmlNewElement(lvl7, "w:lvlText");
        mxmlElementSetAttr(lvltext7, "w:val","");
        mxml_node_t* lvljc7 = mxmlNewElement(lvl7, "w:lvlJc");
        mxmlElementSetAttr(lvljc7,"w:val","left");
        mxml_node_t* ppr7 = mxmlNewElement(lvl7, "w:pPr");
        mxml_node_t* tabs7 = mxmlNewElement(ppr7, "w:tabs");
        mxml_node_t* tab7 = mxmlNewElement(tabs7, "w:tab");
        mxmlElementSetAttr(tab7, "w:pos", "3096");
        mxmlElementSetAttr(tab7, "w:val","num");
        mxml_node_t* ind7 = mxmlNewElement(ppr7, "w:ind");
        mxmlElementSetAttr(ind7, "w:hanging","1296");
        mxmlElementSetAttr(ind7, "w:left", "3096");

        
        mxml_node_t* lvl8 = mxmlNewElement(absnum1, "w:lvl");
        mxmlElementSetAttr(lvl8, "w:ilvl","7");
        mxml_node_t* start8 = mxmlNewElement(lvl8, "w:start");
        mxmlElementSetAttr(start8,"w:val","1");
        mxml_node_t* numFmt8 = mxmlNewElement(lvl8, "w:numFmt");
        mxmlElementSetAttr(numFmt8,"w:val","none");
        mxml_node_t* suff8 = mxmlNewElement(lvl8, "w:suff");
        mxmlElementSetAttr(suff8, "w:val","nothing");
        mxml_node_t* lvltext8 = mxmlNewElement(lvl8, "w:lvlText");
        mxmlElementSetAttr(lvltext8, "w:val","");
        mxml_node_t* lvljc8 = mxmlNewElement(lvl8, "w:lvlJc");
	    mxmlElementSetAttr(lvljc8,"w:val","left");
	    mxml_node_t* ppr8 = mxmlNewElement(lvl8, "w:pPr");
	    mxml_node_t* tabs8 = mxmlNewElement(ppr8, "w:tabs");
	    mxml_node_t* tab8 = mxmlNewElement(tabs8, "w:tab");
	    mxmlElementSetAttr(tab8, "w:pos", "3240");
	    mxmlElementSetAttr(tab8, "w:val","num");
	    mxml_node_t* ind8 = mxmlNewElement(ppr8, "w:ind");
	    mxmlElementSetAttr(ind8, "w:hanging","1440");
	    mxmlElementSetAttr(ind8, "w:left", "3240");

	    mxml_node_t* lvl9 = mxmlNewElement(absnum1, "w:lvl");
	    mxmlElementSetAttr(lvl9, "w:ilvl","8");
	    mxml_node_t* start9 = mxmlNewElement(lvl9, "w:start");
	    mxmlElementSetAttr(start9,"w:val","1");
	    mxml_node_t* numFmt9 = mxmlNewElement(lvl9, "w:numFmt");
	    mxmlElementSetAttr(numFmt9,"w:val","none");
	    mxml_node_t* suff9 = mxmlNewElement(lvl9, "w:suff");
	    mxmlElementSetAttr(suff9, "w:val","nothing");
	    mxml_node_t* lvltext9 = mxmlNewElement(lvl9, "w:lvlText");
	    mxmlElementSetAttr(lvltext9, "w:val","");
	    mxml_node_t* lvljc9 = mxmlNewElement(lvl9, "w:lvlJc");
	    mxmlElementSetAttr(lvljc9,"w:val","left");
	    mxml_node_t* ppr9 = mxmlNewElement(lvl9, "w:pPr");
	    mxml_node_t* tabs9 = mxmlNewElement(ppr9, "w:tabs");
	    mxml_node_t* tab9 = mxmlNewElement(tabs9, "w:tab");
	    mxmlElementSetAttr(tab9, "w:pos", "3384");
	    mxmlElementSetAttr(tab9, "w:val","num");
        mxml_node_t* ind9 = mxmlNewElement(ppr9, "w:ind");
        mxmlElementSetAttr(ind9, "w:hanging","1584");
        mxmlElementSetAttr(ind9, "w:left", "3384");

        mxml_node_t* absnum2 = mxmlNewElement(numbering, "w:abstractNum");
        mxmlElementSetAttr(absnum2, "w:abstractNumId","2");
        mxml_node_t* lvl10 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl10, "w:ilvl","0");
        mxml_node_t* start10 = mxmlNewElement(lvl10, "w:start");
        mxmlElementSetAttr(start10,"w:val","1");
        mxml_node_t* numFmt10 = mxmlNewElement(lvl10, "w:numFmt");
        mxmlElementSetAttr(numFmt10,"w:val","decimal");
        mxml_node_t* lvltext10 = mxmlNewElement(lvl10, "w:lvlText");
        mxmlElementSetAttr(lvltext10, "w:val"," %1 ");
        mxml_node_t* lvljc10 = mxmlNewElement(lvl10, "w:lvlJc");
        mxmlElementSetAttr(lvljc10,"w:val","left");
        mxml_node_t* ppr10 = mxmlNewElement(lvl10, "w:pPr");
        mxml_node_t* tabs10 = mxmlNewElement(ppr10, "w:tabs");
        mxml_node_t* tab10 = mxmlNewElement(tabs10, "w:tab");
        mxmlElementSetAttr(tab10, "w:pos", "720");
        mxmlElementSetAttr(tab10, "w:val","num");
        mxml_node_t* ind10 = mxmlNewElement(ppr10, "w:ind");
        mxmlElementSetAttr(ind10, "w:hanging","360");
        mxmlElementSetAttr(ind10, "w:left", "720");

        mxml_node_t* lvl12 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl12, "w:ilvl","1");
        mxml_node_t* start12 = mxmlNewElement(lvl12, "w:start");
        mxmlElementSetAttr(start12,"w:val","1");
        mxml_node_t* numFmt12 = mxmlNewElement(lvl12, "w:numFmt");
        mxmlElementSetAttr(numFmt12,"w:val","decimal");
        mxml_node_t* lvltext12 = mxmlNewElement(lvl12, "w:lvlText");
        mxmlElementSetAttr(lvltext12, "w:val"," %1.%2 ");
        mxml_node_t* lvljc12 = mxmlNewElement(lvl12, "w:lvlJc");
        mxmlElementSetAttr(lvljc12,"w:val","left");
        mxml_node_t* ppr12 = mxmlNewElement(lvl12, "w:pPr");
        mxml_node_t* tabs12 = mxmlNewElement(ppr12, "w:tabs");
        mxml_node_t* tab12 = mxmlNewElement(tabs12, "w:tab");
        mxmlElementSetAttr(tab12, "w:pos", "1080");
        mxmlElementSetAttr(tab12, "w:val","num");
        mxml_node_t* ind12 = mxmlNewElement(ppr12, "w:ind");
        mxmlElementSetAttr(ind12, "w:hanging","360");
        mxmlElementSetAttr(ind12, "w:left", "1080");

        mxml_node_t* lvl13 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl13, "w:ilvl","2");
        mxml_node_t* start13 = mxmlNewElement(lvl13, "w:start");
        mxmlElementSetAttr(start13,"w:val","1");
        mxml_node_t* numFmt13 = mxmlNewElement(lvl13, "w:numFmt");
        mxmlElementSetAttr(numFmt13,"w:val","bullet");
        mxml_node_t* lvltext13 = mxmlNewElement(lvl13, "w:lvlText");
        mxmlElementSetAttr(lvltext13, "w:val","");
        mxml_node_t* lvljc13 = mxmlNewElement(lvl13, "w:lvlJc");
        mxmlElementSetAttr(lvljc13,"w:val","left");
        mxml_node_t* ppr13 = mxmlNewElement(lvl13, "w:pPr");
        mxml_node_t* tabs13 = mxmlNewElement(ppr13, "w:tabs");
        mxml_node_t* tab13 = mxmlNewElement(tabs13, "w:tab");
        mxmlElementSetAttr(tab13, "w:pos", "1440");
        mxmlElementSetAttr(tab13, "w:val","num");
        mxml_node_t* ind13 = mxmlNewElement(ppr13, "w:ind");
        mxmlElementSetAttr(ind13, "w:hanging","360");
        mxmlElementSetAttr(ind13, "w:left", "1440");
        mxml_node_t* rpr13 = mxmlNewElement(lvl13, "w:rPr");
        mxml_node_t* rfonts13 = mxmlNewElement(rpr13, "w:rFonts");
        mxmlElementSetAttr(rfonts13, "w:ascii","Symbol");
        mxmlElementSetAttr(rfonts13, "w:cs", "Symbol");
        mxmlElementSetAttr(rfonts13, "w:hAnsi","Symbol");
        mxmlElementSetAttr(rfonts13, "w:hint","default");

        mxml_node_t* lvl14 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl14, "w:ilvl","3");
        mxml_node_t* start14 = mxmlNewElement(lvl14, "w:start");
        mxmlElementSetAttr(start14,"w:val","1");
        mxml_node_t* numFmt14 = mxmlNewElement(lvl14, "w:numFmt");
        mxmlElementSetAttr(numFmt14,"w:val","decimal");
        mxml_node_t* lvltext14 = mxmlNewElement(lvl14, "w:lvlText");
        mxmlElementSetAttr(lvltext14, "w:val"," %1.%2.%3.%4 ");
        mxml_node_t* lvljc14 = mxmlNewElement(lvl14, "w:lvlJc");
        mxmlElementSetAttr(lvljc14,"w:val","left");
        mxml_node_t* ppr14 = mxmlNewElement(lvl14, "w:pPr");
        mxml_node_t* tabs14 = mxmlNewElement(ppr14, "w:tabs");
        mxml_node_t* tab14 = mxmlNewElement(tabs14, "w:tab");
        mxmlElementSetAttr(tab14, "w:pos", "1800");
        mxmlElementSetAttr(tab14, "w:val","num");
        mxml_node_t* ind14 = mxmlNewElement(ppr14, "w:ind");
        mxmlElementSetAttr(ind14, "w:hanging","360");
        mxmlElementSetAttr(ind14, "w:left", "1800");

        mxml_node_t* lvl15 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl15, "w:ilvl","4");
        mxml_node_t* start15 = mxmlNewElement(lvl15, "w:start");
        mxmlElementSetAttr(start15,"w:val","1");
        mxml_node_t* numFmt15 = mxmlNewElement(lvl15, "w:numFmt");
        mxmlElementSetAttr(numFmt15,"w:val","decimal");
        mxml_node_t* lvltext15 = mxmlNewElement(lvl15, "w:lvlText");
        mxmlElementSetAttr(lvltext15, "w:val"," %1.%2.%3.%4.%5 ");
        mxml_node_t* lvljc15 = mxmlNewElement(lvl15, "w:lvlJc");
        mxmlElementSetAttr(lvljc15,"w:val","left");
        mxml_node_t* ppr15 = mxmlNewElement(lvl15, "w:pPr");
        mxml_node_t* tabs15 = mxmlNewElement(ppr15, "w:tabs");
        mxml_node_t* tab15 = mxmlNewElement(tabs15, "w:tab");
        mxmlElementSetAttr(tab15, "w:pos", "2160");
        mxmlElementSetAttr(tab15, "w:val","num");
        mxml_node_t* ind15 = mxmlNewElement(ppr15, "w:ind");
        mxmlElementSetAttr(ind15, "w:hanging","360");
        mxmlElementSetAttr(ind15, "w:left", "2160");

        
        mxml_node_t* lvl16 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl16, "w:ilvl","5");
        mxml_node_t* start16 = mxmlNewElement(lvl16, "w:start");
        mxmlElementSetAttr(start16,"w:val","1");
        mxml_node_t* numFmt16 = mxmlNewElement(lvl16, "w:numFmt");
        mxmlElementSetAttr(numFmt16,"w:val","decimal");
        mxml_node_t* lvltext16 = mxmlNewElement(lvl16, "w:lvlText");
        mxmlElementSetAttr(lvltext16, "w:val"," %1.%2.%3.%4.%5.%6 ");
        mxml_node_t* lvljc16 = mxmlNewElement(lvl16, "w:lvlJc");
        mxmlElementSetAttr(lvljc16,"w:val","left");
        mxml_node_t* ppr16 = mxmlNewElement(lvl16, "w:pPr");
        mxml_node_t* tabs16 = mxmlNewElement(ppr16, "w:tabs");
        mxml_node_t* tab16 = mxmlNewElement(tabs16, "w:tab");
        mxmlElementSetAttr(tab16, "w:pos", "2520");
        mxmlElementSetAttr(tab16, "w:val","num");
        mxml_node_t* ind16 = mxmlNewElement(ppr16, "w:ind");
        mxmlElementSetAttr(ind16, "w:hanging","360");
        mxmlElementSetAttr(ind16, "w:left", "2520");

         
        mxml_node_t* lvl17 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl17, "w:ilvl","6");
        mxml_node_t* start17 = mxmlNewElement(lvl17, "w:start");
        mxmlElementSetAttr(start17,"w:val","1");
        mxml_node_t* numFmt17 = mxmlNewElement(lvl17, "w:numFmt");
        mxmlElementSetAttr(numFmt17,"w:val","decimal");
        mxml_node_t* lvltext17 = mxmlNewElement(lvl17, "w:lvlText");
        mxmlElementSetAttr(lvltext17, "w:val"," %1.%2.%3.%4.%5.%6.%7 ");
        mxml_node_t* lvljc17 = mxmlNewElement(lvl17, "w:lvlJc");
        mxmlElementSetAttr(lvljc17,"w:val","left");
        mxml_node_t* ppr17 = mxmlNewElement(lvl17, "w:pPr");
        mxml_node_t* tabs17 = mxmlNewElement(ppr17, "w:tabs");
        mxml_node_t* tab17 = mxmlNewElement(tabs17, "w:tab");
        mxmlElementSetAttr(tab17, "w:pos", "2880");
        mxmlElementSetAttr(tab17, "w:val","num");
        mxml_node_t* ind17 = mxmlNewElement(ppr17, "w:ind");
        mxmlElementSetAttr(ind17, "w:hanging","360");
        mxmlElementSetAttr(ind17, "w:left", "2880");

        mxml_node_t* lvl18 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl18, "w:ilvl","7");
        mxml_node_t* start18 = mxmlNewElement(lvl18, "w:start");
        mxmlElementSetAttr(start18,"w:val","1");
        mxml_node_t* numFmt18 = mxmlNewElement(lvl18, "w:numFmt");
        mxmlElementSetAttr(numFmt18,"w:val","decimal");
        mxml_node_t* lvltext18 = mxmlNewElement(lvl18, "w:lvlText");
        mxmlElementSetAttr(lvltext18, "w:val"," %1.%2.%3.%4.%5.%6.%7.%8 ");
        mxml_node_t* lvljc18 = mxmlNewElement(lvl18, "w:lvlJc");
        mxmlElementSetAttr(lvljc18,"w:val","left");
        mxml_node_t* ppr18 = mxmlNewElement(lvl18, "w:pPr");
        mxml_node_t* tabs18 = mxmlNewElement(ppr18, "w:tabs");
        mxml_node_t* tab18 = mxmlNewElement(tabs18, "w:tab");
        mxmlElementSetAttr(tab18, "w:pos", "3240");
        mxmlElementSetAttr(tab18, "w:val","num");
        mxml_node_t* ind18 = mxmlNewElement(ppr18, "w:ind");
        mxmlElementSetAttr(ind18, "w:hanging","360");
        mxmlElementSetAttr(ind18, "w:left", "3240");

        mxml_node_t* lvl19 = mxmlNewElement(absnum2, "w:lvl");
        mxmlElementSetAttr(lvl19, "w:ilvl","8");
        mxml_node_t* start19 = mxmlNewElement(lvl19, "w:start");
        mxmlElementSetAttr(start19,"w:val","1");
        mxml_node_t* numFmt19 = mxmlNewElement(lvl19, "w:numFmt");
        mxmlElementSetAttr(numFmt19,"w:val","decimal");
        mxml_node_t* lvltext19 = mxmlNewElement(lvl19, "w:lvlText");
        mxmlElementSetAttr(lvltext19, "w:val"," %1.%2.%3.%4.%5.%6.%7.%8.%9 ");
        mxml_node_t* lvljc19 = mxmlNewElement(lvl19, "w:lvlJc");
        mxmlElementSetAttr(lvljc19,"w:val","left");
        mxml_node_t* ppr19 = mxmlNewElement(lvl19, "w:pPr");
        mxml_node_t* tabs19 = mxmlNewElement(ppr19, "w:tabs");
        mxml_node_t* tab19 = mxmlNewElement(tabs19, "w:tab");
        mxmlElementSetAttr(tab19, "w:pos", "3600");
        mxmlElementSetAttr(tab19, "w:val","num");
        mxml_node_t* ind19 = mxmlNewElement(ppr19, "w:ind");
        mxmlElementSetAttr(ind19, "w:hanging","360");
        mxmlElementSetAttr(ind19, "w:left", "3600");


        mxml_node_t* num1 = mxmlNewElement(numbering, "w:num");
        mxmlElementSetAttr(num1, "w:numId","1");
        mxml_node_t* abstractnumid1 = mxmlNewElement(num1, "w:abstractNumId");
        mxmlElementSetAttr(abstractnumid1,"w:val","1");
       
        mxml_node_t* num2 = mxmlNewElement(numbering, "w:num");
        mxmlElementSetAttr(num2, "w:numId","2");
        mxml_node_t* abstractnumid2 = mxmlNewElement(num2, "w:abstractNumId");
        mxmlElementSetAttr(abstractnumid2,"w:val","2");

        return numbering;
}


mxml_node_t* createstyle(mxml_node_t* fstyle)
{
      mxml_node_t* styles = mxmlNewElement(fstyle, "w:styles");
      mxmlElementSetAttr(styles, "xmlns:w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
      mxml_node_t* style1 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style1, "w:styleId", "style0");
      mxmlElementSetAttr(style1, "w:type", "paragraph");
      mxml_node_t* name1 = mxmlNewElement(style1, "w:name");
      mxmlElementSetAttr(name1,"w:val", "Normal");
      mxml_node_t* next1 = mxmlNewElement(style1, "w:next");
      mxmlElementSetAttr(next1, "w:val","style0");
      mxml_node_t* ppr1 = mxmlNewElement(style1, "w:pPr");
      mxml_node_t* wcontrol1 = mxmlNewElement(ppr1, "w:widowControl");
      mxmlElementSetAttr(wcontrol1, "w:val", "false");
      mxml_node_t* tabs1 = mxmlNewElement(ppr1, "w:tabs");
      mxml_node_t* tab1 = mxmlNewElement(tabs1, "w:tab");
      mxmlElementSetAttr(tab1, "w:leader", "none");
      mxmlElementSetAttr(tab1, "w:pos", "709");
      mxmlElementSetAttr(tab1, "w:val", "left");
      mxml_node_t* supautohyphen1 = mxmlNewElement(ppr1, "w:suppressAutoHyphens");
      mxmlElementSetAttr(supautohyphen1, "w:val", "true");
      mxml_node_t* rpr1 = mxmlNewElement(style1, "w:rPr");
      mxml_node_t* rfont1 = mxmlNewElement(rpr1, "w:rFonts");
      mxmlElementSetAttr(rfont1, "w:ascii","Arial");
      mxmlElementSetAttr(rfont1, "w:cs","Arial");
      mxmlElementSetAttr(rfont1, "w:eastAsia","WenQuanYi Micro Hei");
      mxmlElementSetAttr(rfont1, "w:hAnsi","Liberation Serif");
      mxml_node_t* color1 = mxmlNewElement(rpr1, "w:color");
      mxmlElementSetAttr(color1, "w:val","00000A");
      mxml_node_t* sz1 = mxmlNewElement(rpr1, "w:sz");
      mxmlElementSetAttr(sz1, "w:val","18");
      mxml_node_t* szcs1 = mxmlNewElement(rpr1, "w:szCs");
      mxmlElementSetAttr(szcs1, "w:val","18");
      mxml_node_t* lang1 = mxmlNewElement(rpr1, "w:lang");
      mxmlElementSetAttr(lang1, "w:bidi","hi-IN");
      mxmlElementSetAttr(lang1,"w:eastAsia","zh-CN");
      mxmlElementSetAttr(lang1,"w:val","en-IN");

                         
      mxml_node_t* style2 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style2, "w:styleId", "style1");
      mxmlElementSetAttr(style2, "w:type", "paragraph");
      mxml_node_t* name2 = mxmlNewElement(style2, "w:name");
      mxmlElementSetAttr(name2,"w:val", "Heading 1");
      mxml_node_t* basedon2 = mxmlNewElement(style2, "w:basedOn");
      mxmlElementSetAttr(basedon2,"w:val","style18");
      mxml_node_t* next2 = mxmlNewElement(style2, "w:next");
      mxmlElementSetAttr(next2, "w:val","style19");
      mxml_node_t* ppr2 = mxmlNewElement(style2, "w:pPr");
      mxml_node_t* numpr2 = mxmlNewElement(ppr2, "w:numPr");
      mxml_node_t* ilvl2 = mxmlNewElement(numpr2, "w:ilvl");
      mxmlElementSetAttr(ilvl2, "w:val", "0");
      mxml_node_t* numid2 = mxmlNewElement(numpr2,"w:numId");
      mxmlElementSetAttr(numid2, "w:val", "1");
      mxml_node_t* outlinelvl2 = mxmlNewElement(ppr2, "w:outlineLvl");
      mxmlElementSetAttr(outlinelvl2, "w:val", "0");
      mxml_node_t* rpr2 = mxmlNewElement(style2, "w:rPr");
      mxml_node_t* rfont_ = mxmlNewElement(rpr2, "w:rFonts");
      mxmlElementSetAttr(rfont_, "w:ascii","Arial");
      mxmlElementSetAttr(rfont_, "w:cs","Arial");
      mxmlNewElement(rpr2, "w:b");
      mxmlNewElement(rpr2, "w:bCs");
      mxml_node_t* sz2 = mxmlNewElement(rpr2, "w:sz");
      mxmlElementSetAttr(sz2, "w:val","32");
      mxml_node_t* szcs2 = mxmlNewElement(rpr2, "w:szCs");
      mxmlElementSetAttr(szcs2, "w:val","32");

      mxml_node_t* style3 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style3, "w:styleId", "style15");
      mxmlElementSetAttr(style3, "w:type", "character");
      mxml_node_t* name3 = mxmlNewElement(style3, "w:name");
      mxmlElementSetAttr(name3,"w:val", "Bullets");
      mxml_node_t* next3 = mxmlNewElement(style3, "w:next");
      mxmlElementSetAttr(next3, "w:val","style15");
      mxml_node_t* rpr3 = mxmlNewElement(style3, "w:rPr");
      mxml_node_t* rfont3 = mxmlNewElement(rpr3, "w:rFonts");
      mxmlElementSetAttr(rfont3, "w:ascii","Arial");
      mxmlElementSetAttr(rfont3, "w:cs","Arial");
      mxmlElementSetAttr(rfont3, "w:eastAsia","OpenSymbol");
      mxmlElementSetAttr(rfont3, "w:hAnsi","OpenSymbol");

      mxml_node_t* style4 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style4, "w:styleId", "style16");
      mxmlElementSetAttr(style4, "w:type", "character");
      mxml_node_t* name4 = mxmlNewElement(style4, "w:name");
      mxmlElementSetAttr(name4,"w:val", "ListLabel 1");
      mxml_node_t* next4 = mxmlNewElement(style4, "w:next");
      mxmlElementSetAttr(next4, "w:val","style16");
      mxml_node_t* rpr4 = mxmlNewElement(style4, "w:rPr");
      mxml_node_t* rfont4 = mxmlNewElement(rpr4, "w:rFonts");
      mxmlElementSetAttr(rfont4, "w:ascii","Arial");
      mxmlElementSetAttr(rfont4, "w:cs","Arial");

      mxml_node_t* style5 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style5, "w:styleId", "style17");
      mxmlElementSetAttr(style5, "w:type", "character");
      mxml_node_t* name5 = mxmlNewElement(style5, "w:name");
      mxmlElementSetAttr(name5,"w:val", "ListLabel 2");
      mxml_node_t* next5 = mxmlNewElement(style5, "w:next");
      mxmlElementSetAttr(next5, "w:val","style17");
      mxml_node_t* rpr5 = mxmlNewElement(style5, "w:rPr");
      mxml_node_t* rfont5 = mxmlNewElement(rpr5, "w:rFonts");
      mxmlElementSetAttr(rfont5, "w:ascii","Arial");
      mxmlElementSetAttr(rfont5, "w:cs","Arial");

      mxml_node_t* _style = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(_style, "w:styleId", "_style");
      mxmlElementSetAttr(_style, "w:type", "paragraph");
      mxml_node_t* _name = mxmlNewElement(_style, "w:name");
      mxmlElementSetAttr(_name,"w:val", "Heading");
      mxml_node_t* _basedon = mxmlNewElement(_style, "w:basedOn");
      mxmlElementSetAttr(_basedon,"w:val","style0");
      mxml_node_t* _next = mxmlNewElement(_style, "w:next");
      mxmlElementSetAttr(_next, "w:val","style19");
      mxml_node_t* _ppr = mxmlNewElement(_style, "w:pPr");
      mxmlNewElement(_ppr, "w:keepNext");
      mxml_node_t* _spacing = mxmlNewElement(_ppr,"w:spacing");
      mxmlElementSetAttr(_spacing,"w:after","120");
      mxmlElementSetAttr(_spacing,"w:before","240");
      mxml_node_t* _rpr = mxmlNewElement(_style, "w:rPr");
      mxml_node_t* _rfont = mxmlNewElement(_rpr, "w:rFonts");
      mxmlElementSetAttr(_rfont, "w:ascii","Arial");
      mxmlElementSetAttr(_rfont, "w:cs","Arial");
      mxmlElementSetAttr(_rfont, "w:eastAsia","WenQuanYi Micro Hei");
      mxmlElementSetAttr(_rfont, "w:hAnsi","Liberation Sans");
      mxml_node_t* _sz = mxmlNewElement(_rpr, "w:sz");
      mxmlElementSetAttr(_sz, "w:val","24");
      mxml_node_t* _szcs = mxmlNewElement(_rpr, "w:szCs");
      mxmlElementSetAttr(_szcs, "w:val","24");

      mxml_node_t* style6 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style6, "w:styleId", "style18");
      mxmlElementSetAttr(style6, "w:type", "paragraph");
      mxml_node_t* name6 = mxmlNewElement(style6, "w:name");
      mxmlElementSetAttr(name6,"w:val", "Heading");
      mxml_node_t* basedon6 = mxmlNewElement(style6, "w:basedOn");
      mxmlElementSetAttr(basedon6,"w:val","style0");
      mxml_node_t* next6 = mxmlNewElement(style6, "w:next");
      mxmlElementSetAttr(next6, "w:val","style19");
      mxml_node_t* ppr6 = mxmlNewElement(style6, "w:pPr");
      mxmlNewElement(ppr6, "w:keepNext");
      mxml_node_t* spacing6 = mxmlNewElement(ppr6,"w:spacing");
      mxmlElementSetAttr(spacing6,"w:after","120");
      mxmlElementSetAttr(spacing6,"w:before","240");
      mxml_node_t* rpr6 = mxmlNewElement(style6, "w:rPr");
      mxml_node_t* rfont6 = mxmlNewElement(rpr6, "w:rFonts");
      mxmlElementSetAttr(rfont6, "w:ascii","Arial");
      mxmlElementSetAttr(rfont6, "w:cs","Arial");
      mxmlElementSetAttr(rfont6, "w:eastAsia","WenQuanYi Micro Hei");
      mxmlElementSetAttr(rfont6, "w:hAnsi","Liberation Sans");
      mxml_node_t* sz6 = mxmlNewElement(rpr6, "w:sz");
      mxmlElementSetAttr(sz6, "w:val","28");
      mxml_node_t* szcs6 = mxmlNewElement(rpr6, "w:szCs");
      mxmlElementSetAttr(szcs6, "w:val","28");
        
      mxml_node_t* style7 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style7, "w:styleId", "style19");
      mxmlElementSetAttr(style7, "w:type", "paragraph");
      mxml_node_t* name7 = mxmlNewElement(style7, "w:name");
      mxmlElementSetAttr(name7,"w:val", "Text body");
      mxml_node_t* basedon7 = mxmlNewElement(style7, "w:basedOn");
      mxmlElementSetAttr(basedon7,"w:val","style0");
      mxml_node_t* next7 = mxmlNewElement(style7, "w:next");
      mxmlElementSetAttr(next7, "w:val","style19");
      mxml_node_t* ppr7 = mxmlNewElement(style7, "w:pPr");
      mxml_node_t* spacing7 = mxmlNewElement(ppr7,"w:spacing");
      mxmlElementSetAttr(spacing7,"w:after","120");
      mxmlElementSetAttr(spacing7,"w:before","0");
      mxmlNewElement(style7, "w:rPr");

      mxml_node_t* style8 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style8, "w:styleId", "style20");
      mxmlElementSetAttr(style8, "w:type", "paragraph");
      mxml_node_t* name8 = mxmlNewElement(style8, "w:name");
      mxmlElementSetAttr(name8,"w:val", "List");
      mxml_node_t* basedon8 = mxmlNewElement(style8, "w:basedOn");
      mxmlElementSetAttr(basedon8,"w:val","style19");
      mxml_node_t* next8 = mxmlNewElement(style8, "w:next");
      mxmlElementSetAttr(next8, "w:val","style20");
      mxmlNewElement(style8, "w:pPr");
      mxml_node_t* rpr8 = mxmlNewElement(style8, "w:rPr");
      mxml_node_t* rfont8 = mxmlNewElement(rpr8, "w:rFonts");
      mxmlElementSetAttr(rfont8, "w:cs","Arial");
		


      mxml_node_t* style9 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style9, "w:styleId", "style21");
      mxmlElementSetAttr(style9, "w:type", "paragraph");
      mxml_node_t* name9 = mxmlNewElement(style9, "w:name");
      mxmlElementSetAttr(name9,"w:val", "Caption");
      mxml_node_t* basedon9 = mxmlNewElement(style9, "w:basedOn");
      mxmlElementSetAttr(basedon9,"w:val","style0");
      mxml_node_t* next9 = mxmlNewElement(style9, "w:next");
      mxmlElementSetAttr(next9, "w:val","style21");
      mxml_node_t* ppr9 = mxmlNewElement(style9, "w:pPr");
      mxmlNewElement(ppr9, "w:suppressLineNumbers");
      mxml_node_t* spacing9 = mxmlNewElement(ppr9, "w:spacing");
      mxmlElementSetAttr(spacing9,"w:after","120");
      mxmlElementSetAttr(spacing9,"w:before","120");
      mxml_node_t* rpr9 = mxmlNewElement(style9, "w:rPr");
      mxml_node_t* rfont9 = mxmlNewElement(rpr9, "w:rFonts");
      mxmlElementSetAttr(rfont9, "w:cs","Arial");
      mxmlNewElement(rpr9, "w:i");
      mxmlNewElement(rpr9, "w:iCs");
      mxml_node_t* sz9 = mxmlNewElement(rpr9, "w:sz");
      mxmlElementSetAttr(sz9, "w:val","18");
      mxml_node_t* szcs9 = mxmlNewElement(rpr9, "w:szCs");
      mxmlElementSetAttr(szcs9, "w:val","18");



      mxml_node_t* style10 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style10, "w:styleId", "style22");
      mxmlElementSetAttr(style10, "w:type", "paragraph");
      mxml_node_t* name10 = mxmlNewElement(style10, "w:name");
      mxmlElementSetAttr(name10,"w:val", "Index");
      mxml_node_t* basedon10 = mxmlNewElement(style10, "w:basedOn");
      mxmlElementSetAttr(basedon10,"w:val","style0");
      mxml_node_t* next10 = mxmlNewElement(style10, "w:next");
      mxmlElementSetAttr(next10, "w:val","style22");
      mxml_node_t* ppr10 = mxmlNewElement(style10, "w:pPr");
      mxmlNewElement(ppr10, "w:suppressLineNumbers");
      mxml_node_t* rpr10 = mxmlNewElement(style10, "w:rPr");
      mxml_node_t* rfont10 = mxmlNewElement(rpr10, "w:rFonts");
      mxmlElementSetAttr(rfont10, "w:cs","Arial");
                
      mxml_node_t* style11 = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style11, "w:styleId", "style23");
      mxmlElementSetAttr(style11, "w:type", "paragraph");
      mxml_node_t* name11 = mxmlNewElement(style11, "w:name");
      mxmlElementSetAttr(name11,"w:val", "Header");
      mxml_node_t* basedon11 = mxmlNewElement(style11, "w:basedOn");
      mxmlElementSetAttr(basedon11,"w:val","style0");
      mxml_node_t* next11 = mxmlNewElement(style11, "w:next");
      mxmlElementSetAttr(next11, "w:val","style23");
      mxml_node_t* ppr11 = mxmlNewElement(style11, "w:pPr");
      mxmlNewElement(ppr11, "w:suppressLineNumbers");
      mxml_node_t* tabs11 = mxmlNewElement(ppr11, "w:tabs");
      mxml_node_t* tab11 = mxmlNewElement(tabs11,"w:tab");
      mxmlElementSetAttr(tab11,"w:leader", "none");
      mxmlElementSetAttr(tab11,"w:pos", "4819");
      mxmlElementSetAttr(tab11,"w:val", "center");
      mxml_node_t* tab12 = mxmlNewElement(tabs11,"w:tab");
      mxmlElementSetAttr(tab12,"w:leader", "none");
      mxmlElementSetAttr(tab12,"w:pos", "9638");
      mxmlElementSetAttr(tab12,"w:val", "right");
      mxmlNewElement(style11, "w:rPr");


       
      mxml_node_t* style_ = mxmlNewElement(styles, "w:style");
      mxmlElementSetAttr(style_, "w:styleId", "style_");
      mxmlElementSetAttr(style_, "w:type", "paragraph");
      mxml_node_t* name_ = mxmlNewElement(style_, "w:name");
      mxmlElementSetAttr(name_,"w:val", "Heading 1");
      mxml_node_t* basedon_ = mxmlNewElement(style_, "w:basedOn");
      mxmlElementSetAttr(basedon_,"w:val","style18");
      mxml_node_t* next_ = mxmlNewElement(style_, "w:next");
      mxmlElementSetAttr(next_, "w:val","style19");
      mxml_node_t* ppr_ = mxmlNewElement(style_, "w:pPr");
      mxml_node_t* numpr_ = mxmlNewElement(ppr_, "w:numPr");
      mxml_node_t* ilvl_ = mxmlNewElement(numpr_, "w:ilvl");
      mxmlElementSetAttr(ilvl_, "w:val", "0");
      mxml_node_t* numid_ = mxmlNewElement(numpr_,"w:numId");
      mxmlElementSetAttr(numid_, "w:val", "1");
      mxml_node_t* outlinelvl_ = mxmlNewElement(ppr_, "w:outlineLvl");
      mxmlElementSetAttr(outlinelvl_, "w:val", "0");
      mxml_node_t* rpr_ = mxmlNewElement(style_, "w:rPr");
      mxml_node_t* _rfont_ = mxmlNewElement(rpr_, "w:rFonts");
      mxmlElementSetAttr(_rfont_, "w:ascii","Arial");
      mxmlElementSetAttr(_rfont_, "w:cs","Arial");
      mxmlNewElement(rpr_, "w:b");
      mxmlNewElement(rpr_, "w:bCs");
      mxml_node_t* sz2_ = mxmlNewElement(rpr_, "w:sz");
      mxmlElementSetAttr(sz2_, "w:val","32");
      mxml_node_t* szcs2_ = mxmlNewElement(rpr_, "w:szCs");
      mxmlElementSetAttr(szcs2_, "w:val","32");
      return styles;
}

//call lib
mxml_node_t* createfont(mxml_node_t* fonth)
{
		mxml_node_t* fonts = mxmlNewElement(fonth, "w:fonts");
		mxmlElementSetAttr(fonts, "xmlns:w","http://schemas.openxmlformats.org/wordprocessingml/2006/main");
				
		mxml_node_t* font1 = mxmlNewElement(fonts, "w:font");
		mxmlElementSetAttr(font1,"w:name","Times New Roman");
		mxml_node_t* cs1 = mxmlNewElement(font1, "w:charset");
		mxmlElementSetAttr(cs1,"w:val","00");
		mxml_node_t* family1 = mxmlNewElement(font1, "w:family");
		mxmlElementSetAttr(family1,"w:val","roman");
		mxml_node_t* pitch1 = mxmlNewElement(font1,"w:pitch");
		mxmlElementSetAttr(pitch1,"w:val","variable");
	
		mxml_node_t* font2 = mxmlNewElement(fonts, "w:font");
		mxmlElementSetAttr(font2,"w:name","Symbol");
		mxml_node_t* cs2 = mxmlNewElement(font2, "w:charset");
		mxmlElementSetAttr(cs2,"w:val","02");
		mxmlNewElement(font2, "w:family");
		mxmlElementSetAttr(family1,"w:val","roman");
		mxml_node_t* pitch2 = mxmlNewElement(font2,"w:pitch");
		mxmlElementSetAttr(pitch2,"w:val","variable");


		mxml_node_t* font3 = mxmlNewElement(fonts, "w:font");
		mxmlElementSetAttr(font3,"w:name","Arial");
		mxml_node_t* cs3 = mxmlNewElement(font3, "w:charset");
		mxmlElementSetAttr(cs3,"w:val","00");
		mxml_node_t* family3 = mxmlNewElement(font3, "w:family");
		mxmlElementSetAttr(family3,"w:val","swiss");
		mxml_node_t* pitch3 = mxmlNewElement(font3,"w:pitch");
		mxmlElementSetAttr(pitch3,"w:val","variable");


		mxml_node_t* font4 = mxmlNewElement(fonts, "w:font");
		mxmlElementSetAttr(font4,"w:name","Liberation Serif");
		mxml_node_t* altname4 = mxmlNewElement(font4, "w:altName");
		mxmlElementSetAttr(altname4,"w:val","Times New Roman");
		mxml_node_t* cs4 = mxmlNewElement(font4, "w:charset");
		mxmlElementSetAttr(cs4,"w:val","80");
		mxml_node_t* family4 = mxmlNewElement(font4, "w:family");
		mxmlElementSetAttr(family4,"w:val","roman");
		mxml_node_t* pitch4 = mxmlNewElement(font4,"w:pitch");
		mxmlElementSetAttr(pitch4,"w:val","variable");


		mxml_node_t* font5 = mxmlNewElement(fonts, "w:font");
		mxmlElementSetAttr(font5,"w:name","Symbol");
		mxml_node_t* cs5 = mxmlNewElement(font5, "w:charset");
		mxmlElementSetAttr(cs5,"w:val","02");
		mxml_node_t* family5 = mxmlNewElement(font5, "w:family");
		mxmlElementSetAttr(family5,"w:val","auto");
		mxml_node_t* pitch5 = mxmlNewElement(font5,"w:pitch");
		mxmlElementSetAttr(pitch5,"w:val","variable");
		return fonts;
}

//call lib
mxml_node_t* createreference(mxml_node_t* refh)
{
   mxml_node_t* relationships= mxmlNewElement(refh, "Relationships");
       mxmlElementSetAttr(relationships,"xmlns","http://schemas.openxmlformats.org/package/2006/relationships");
       mxml_node_t* rel1= mxmlNewElement(relationships, "Relationship");	
       mxmlElementSetAttr(rel1,"Id","rId1");
       mxmlElementSetAttr(rel1,"Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles");
       mxmlElementSetAttr(rel1,"Target","styles.xml");	
       
       mxml_node_t* rel2= mxmlNewElement(relationships, "Relationship");	
       mxmlElementSetAttr(rel2,"Id","rId2");
       mxmlElementSetAttr(rel2,"Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/header");
       mxmlElementSetAttr(rel2,"Target","header1.xml");	
       
       mxml_node_t* rel3= mxmlNewElement(relationships, "Relationship");	
       mxmlElementSetAttr(rel3,"Id","rId3");
       mxmlElementSetAttr(rel3,"Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering");
       mxmlElementSetAttr(rel3,"Target","numbering.xml");
       
       mxml_node_t* rel4= mxmlNewElement(relationships, "Relationship");	
       mxmlElementSetAttr(rel4,"Id","rId4");
       mxmlElementSetAttr(rel4,"Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable");
       mxmlElementSetAttr(rel4,"Target","fontTable.xml");	
       
       mxml_node_t* rel5= mxmlNewElement(relationships, "Relationship");	
       mxmlElementSetAttr(rel5,"Id","rId5");
       mxmlElementSetAttr(rel5,"Type","http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer");
       mxmlElementSetAttr(rel5,"Target","footer1.xml");	   
       
       return relationships;
	
}

//call lib
mxml_node_t* createfooter(mxml_node_t* fdrd)
{
	mxml_node_t* ftr = mxmlNewElement(fdrd, "w:ftr");
	mxmlElementSetAttr(ftr,"xmlns:o","urn:schemas-microsoft-com:office:office");
	mxmlElementSetAttr(ftr,"xmlns:r","http://schemas.openxmlformats.org/officeDocument/2006/relationships");
	mxmlElementSetAttr(ftr,"xmlns:v","urn:schemas-microsoft-com:vml");
	mxmlElementSetAttr(ftr,"xmlns:w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
	mxmlElementSetAttr(ftr,"xmlns:w10","urn:schemas-microsoft-com:office:word");	
	mxmlElementSetAttr(ftr,"xmlns:wp","http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");
	mxml_node_t* p = mxmlNewElement(ftr, "w:p");
	mxml_node_t* ppr = mxmlNewElement(p, "w:pPr");
	mxml_node_t* pstyle = mxmlNewElement(ppr, "w:pStyle");
	mxmlElementSetAttr(pstyle, "w:val", "Footer");
	mxml_node_t* r = mxmlNewElement(p, "w:r");
	mxmlNewElement(r, "w:rPr");
	mxml_node_t* rfont1 = mxmlNewElement(r, "w:rFonts");
	mxmlElementSetAttr(rfont1, "w:ascii","Arial");
	mxmlElementSetAttr(rfont1, "w:cs","Arial");
		
	mxml_node_t* t = mxmlNewElement(r, "w:t");
	mxmlElementSetAttr(t, "xml:space","preserve");
	mxmlNewText(t, 0, "Oss Component Clearing Report Draft 3.2 Siemens");
	return ftr;
}


//call lib
mxml_node_t* createheader(mxml_node_t* hdrd)
{
	mxml_node_t* hdr = mxmlNewElement(hdrd, "w:hdr");
	
	mxmlElementSetAttr(hdr,"xmlns:o","urn:schemas-microsoft-com:office:office");
	mxmlElementSetAttr(hdr,"xmlns:r","http://schemas.openxmlformats.org/officeDocument/2006/relationships");
	mxmlElementSetAttr(hdr,"xmlns:v","urn:schemas-microsoft-com:vml");
	mxmlElementSetAttr(hdr,"xmlns:w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
	mxmlElementSetAttr(hdr,"xmlns:w10","urn:schemas-microsoft-com:office:word");	
	mxmlElementSetAttr(hdr,"xmlns:wp","http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");

	mxml_node_t* p = mxmlNewElement(hdr, "w:p");
	mxml_node_t* ppr = mxmlNewElement(p, "w:pPr");
	mxml_node_t* pstyle = mxmlNewElement(ppr, "w:pStyle");
	mxmlElementSetAttr(pstyle, "w:val", "style20");
	mxml_node_t* r = mxmlNewElement(p, "w:r");
	mxmlNewElement(r, "w:rPr");
	mxml_node_t* rfont1 = mxmlNewElement(r, "w:rFonts");
	mxmlElementSetAttr(rfont1, "w:ascii","Arial");
	mxmlElementSetAttr(rfont1, "w:cs","Arial");
		
	mxml_node_t* t = mxmlNewElement(r, "w:t");
	mxmlNewText(t, 0, "SIEMENS");

	return hdr;
}
//call lib
void createsectionptr(mxml_node_t* body)
{

		mxml_node_t* secptr = mxmlNewElement(body, "w:sectPr");
		mxml_node_t* hdref = mxmlNewElement(secptr, "w:headerReference");
		mxmlElementSetAttr(hdref, "r:id", "rId2");
		mxmlElementSetAttr(hdref, "w:type", "default");
		mxml_node_t* ftref1 = mxmlNewElement(secptr, "w:footerReference");
		mxmlElementSetAttr(ftref1, "r:id", "rId5");
		mxmlElementSetAttr(ftref1, "w:type", "default");
		mxml_node_t* type = mxmlNewElement(secptr, "w:type");
		mxmlElementSetAttr(type, "w:val", "nextPage");
		mxml_node_t* pgsz = mxmlNewElement(secptr, "w:pgSz");
		mxmlElementSetAttr(pgsz, "w:h", "16838");
		mxmlElementSetAttr(pgsz, "w:w", "11906");
		mxml_node_t* pgmar = mxmlNewElement(secptr, "w:pgMar");
		mxmlElementSetAttr(pgmar, "w:bottom", "1134");
		mxmlElementSetAttr(pgmar, "w:footer", "1134");
		mxmlElementSetAttr(pgmar, "w:gutter", "0");
		mxmlElementSetAttr(pgmar, "w:header", "1134");
		mxmlElementSetAttr(pgmar, "w:left", "1134");
		mxmlElementSetAttr(pgmar, "w:right", "1134");
		mxmlElementSetAttr(pgmar, "w:top", "1134");
		mxml_node_t* pgnumtype = mxmlNewElement(secptr, "w:pgNumType");
		mxmlElementSetAttr(pgnumtype, "w:fmt", "decimal");
		mxmlNewElement(secptr, "w:formProt");
		mxmlElementSetAttr(pgnumtype, "w:val", "true");
		mxmlElementSetAttr(pgnumtype, "w:start", "1");
		mxmlElementSetAttr(pgnumtype, "w:val", "lrTb");
}

//call lib
mxml_node_t* createbodyheader(mxml_node_t* xml)
{

         
       mxml_node_t* doc = mxmlNewElement(xml, "w:document");
	 
       mxmlElementSetAttr(doc,"xmlns:ve", "http://schemas.openxmlformats.org/markup-compatibility/2006");
       mxmlElementSetAttr(doc,"xmlns:o", "urn:schemas-microsoft-com:office:office");
       mxmlElementSetAttr(doc,"xmlns:r", "http://schemas.openxmlformats.org/officeDocument/2006/relationships");
       mxmlElementSetAttr(doc,"xmlns:m", "http://lschemas.openxmlformats.org/officeDocument/2006/math");
       mxmlElementSetAttr(doc,"xmlns:v", "urn:schemas-microsoft-com:vml");
       mxmlElementSetAttr(doc,"xmlns:wp", "http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");
       mxmlElementSetAttr(doc,"xmlns:w10", "urn:schemas-microsoft-com:office:word");
       mxmlElementSetAttr(doc,"xmlns:w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
       mxmlElementSetAttr(doc,"xmlns:wne", "http://schemas.microsoft.com/office/word/2006/wordml");
       return doc;
}

//call lib
void addheading(mxml_node_t* body, char* Headingname)
{
  	mxml_node_t* p = mxmlNewElement(body, "w:p");
  	mxml_node_t* ppr = mxmlNewElement(p, "w:pPr");
	mxml_node_t* jc = mxmlNewElement(ppr, "w:jc");
    mxmlElementSetAttr(jc, "w:val", "center");
    mxml_node_t* pstyle = mxmlNewElement(ppr,"w:pStyle");
    mxmlElementSetAttr(pstyle,"w:val","Heading1");
    mxml_node_t* r = mxmlNewElement(p, "w:r");
	mxml_node_t* rpr=mxmlNewElement(r, "w:rPr");
	mxmlNewElement(rpr, "w:b");
	mxmlNewElement(rpr, "w:bCs");
	mxml_node_t* sz1 = mxmlNewElement(rpr, "w:sz");
	mxmlElementSetAttr(sz1, "w:val","28");
	mxml_node_t* sz2 = mxmlNewElement(rpr, "w:sz");
	mxmlElementSetAttr(sz2, "w:val","28");
        
	mxml_node_t* rfont1 = mxmlNewElement(r, "w:rFonts");
	mxmlElementSetAttr(rfont1, "w:ascii","Arial");
	mxmlElementSetAttr(rfont1, "w:cs","Arial");
		
	mxml_node_t* t = mxmlNewElement(r, "w:t");
	mxmlNewText(t, 0, Headingname);
	

}

void addrPr(mxml_node_t* tr)
{
	mxml_node_t* trPr1 = mxmlNewElement(tr, "w:trPr");
		mxml_node_t* split = mxmlNewElement(trPr1,"w:cantSplit");
		mxmlElementSetAttr(split, "w:val", "false");
}

	

mxml_node_t* createrowproperty(mxml_node_t* tbl)
{	
	mxml_node_t* tr = mxmlNewElement(tbl, "w:tr");
	addrPr(tr);
	return tr;
}

//call lib
mxml_node_t* createtable(mxml_node_t* body, char* totalwidth)
{
		mxml_node_t* tbl = mxmlNewElement(body, "w:tbl");
		mxml_node_t* tblpr = mxmlNewElement(tbl, "w:tblPr");
        mxml_node_t* tblBorders = mxmlNewElement(tblpr, "w:tblBorders");
        mxml_node_t* top = mxmlNewElement(tblBorders, "w:top");
      	mxmlElementSetAttr(top, "w:val", "single");
      	mxmlElementSetAttr(top, "w:sz", "4");
      	mxmlElementSetAttr(top, "w:space", "0");
      	mxmlElementSetAttr(top, "w:color", "auto");

        mxml_node_t* left = mxmlNewElement(tblBorders, "w:left");
      	mxmlElementSetAttr(left, "w:val", "single");
      	mxmlElementSetAttr(left, "w:sz", "4");
      	mxmlElementSetAttr(left, "w:space", "0");
      	mxmlElementSetAttr(left, "w:color", "auto");

        mxml_node_t* bottom = mxmlNewElement(tblBorders, "w:bottom");
      	mxmlElementSetAttr(bottom, "w:val", "single");
      	mxmlElementSetAttr(bottom, "w:sz", "4");
      	mxmlElementSetAttr(bottom, "w:space", "0");
      	mxmlElementSetAttr(bottom, "w:color", "auto");

        mxml_node_t* right = mxmlNewElement(tblBorders, "w:right");
      	mxmlElementSetAttr(right, "w:val", "single");
      	mxmlElementSetAttr(right, "w:sz", "4");
      	mxmlElementSetAttr(right, "w:space", "0");
      	mxmlElementSetAttr(right, "w:color", "auto");
	
	
        mxml_node_t* insideH = mxmlNewElement(tblBorders, "w:insideH");
      	mxmlElementSetAttr(insideH, "w:val", "single");
      	mxmlElementSetAttr(insideH, "w:sz", "4");
      	mxmlElementSetAttr(insideH, "w:space", "0");
      	mxmlElementSetAttr(insideH, "w:color", "auto"); 

	
        mxml_node_t* insideV = mxmlNewElement(tblBorders, "w:insideV");
      	mxmlElementSetAttr(insideV, "w:val", "single");
      	mxmlElementSetAttr(insideV, "w:sz", "4");
      	mxmlElementSetAttr(insideV, "w:space", "0");
      	mxmlElementSetAttr(insideV, "w:color", "auto"); 

      	mxml_node_t* tcw = mxmlNewElement(tblpr, "w:tblW");
      	mxmlElementSetAttr(tcw, "w:type", "auto");
      	mxmlElementSetAttr(tcw, "w:w", totalwidth);
      	return tbl;
}
//call lib
void createtablegrid(mxml_node_t* tbl,char** gridwidth,  int cols)
{
	int c;
	mxml_node_t* tblgrid = mxmlNewElement(tbl, "w:tblGrid");
	for(c=0; c<cols;c++)
	{
			mxml_node_t* grid = mxmlNewElement(tblgrid, "w:gridCol");
			mxmlElementSetAttr(grid, "w:w", gridwidth[c]);
	}
}

void createcelldataproperty(mxml_node_t* tc, char* width)
{
	mxml_node_t* tcPr = mxmlNewElement(tc, "w:tcPr");
	mxml_node_t* tcW = mxmlNewElement(tcPr, "w:tcW");
	mxmlElementSetAttr(tcW, "w:type", "dxa");
	mxmlElementSetAttr(tcW, "w:w", width);
}

void addcelldata(mxml_node_t* tc, char* celldata)
{
	mxml_node_t* p1 = mxmlNewElement(tc, "w:p");
	mxml_node_t* ppr1 = mxmlNewElement(p1, "w:pPr");
	mxml_node_t* pstyle1 = mxmlNewElement(ppr1, "w:pStyle");
	mxmlElementSetAttr(pstyle1, "w:val", "style20");
	mxml_node_t* r01 = mxmlNewElement(p1, "w:r");
	mxmlNewElement(r01, "w:rPr");
	mxml_node_t* rfont1 = mxmlNewElement(r01, "w:rFonts");
	mxmlElementSetAttr(rfont1, "w:ascii","Arial");
	mxmlElementSetAttr(rfont1, "w:cs","Arial");
	mxml_node_t* t01 = mxmlNewElement(r01,"w:t");
	mxmlNewText(t01, 0, celldata);
}

//call lib
void createrowdata(mxml_node_t* tr, char* cellwidth, char* celldata)
{
	mxml_node_t* tc = NULL;
	tc = mxmlNewElement(tr, "w:tc");
	createcelldataproperty(tc, cellwidth);
	addcelldata(tc, celldata);
}

/*Numbered Section*/

void addparaproperty(mxml_node_t* p, char* lvl, char* numidv)
{
	mxml_node_t* ppr = mxmlNewElement(p, "w:pPr");
	mxml_node_t* pstyle = mxmlNewElement(ppr,"w:pStyle");
	mxmlElementSetAttr(pstyle, "w:val", "style0");
	mxml_node_t* pnumpr = mxmlNewElement(ppr, "w:numPr");
	mxml_node_t* ilvl = mxmlNewElement(pnumpr, "w:ilvl");
	mxmlElementSetAttr(ilvl, "w:val", lvl);
	mxml_node_t* numid = mxmlNewElement(pnumpr, "w:numId");
	mxmlElementSetAttr(numid, "w:val", numidv);
}

void addparagraph(mxml_node_t* body,char* italics, char* text)
{
		mxml_node_t* p = mxmlNewElement(body, "w:p");
		mxml_node_t* r = mxmlNewElement(p, "w:r");
		mxml_node_t* rpr = mxmlNewElement(r, "w:rPr");
		mxml_node_t* rfont1 = mxmlNewElement(r, "w:rFonts");
		mxmlElementSetAttr(rfont1, "w:ascii","Arial");
		mxmlElementSetAttr(rfont1, "w:cs","Arial");
		mxml_node_t* sz0 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(sz0, "w:val", "24");
		mxml_node_t* szcs0 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(szcs0, "w:val", "24");
		if(italics != NULL)
		{
			if(strcmp(italics, "I") == 0)
			{
				mxmlNewElement(rpr, "w:i");
			}
		}
	    mxml_node_t* t = mxmlNewElement(r, "w:t");
		mxmlElementSetAttr(t, "xml:space", "preserve");
		mxmlNewText(t, 0, text);
}

//call lib
void addparaheading(mxml_node_t* p, char* italics, char* heading,  char* lvl, char* numid)
{
//new code
	mxml_node_t* r = mxmlNewElement(p, "w:r");
	mxml_node_t* rpr = mxmlNewElement(r, "w:rPr");
	mxml_node_t* rfont1 = mxmlNewElement(r, "w:rFonts");
	mxmlElementSetAttr(rfont1, "w:ascii","Arial");
	mxmlElementSetAttr(rfont1, "w:cs","Arial");

	if((strcmp(lvl, "0")==0) && (strcmp(numid, "2")==0))
	{
		mxmlNewElement(rpr, "w:b");
		mxmlNewElement(rpr, "w:bCs");
		mxml_node_t* sz0 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(sz0, "w:val", "32");
		mxml_node_t* szcs0 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(szcs0, "w:val", "32");
	}
	else if((strcmp(lvl, "1")==0) && (strcmp(numid, "2")==0))
	{
		mxmlNewElement(rpr, "w:i");
		mxmlNewElement(rpr, "w:iCs");
		mxml_node_t* sz1 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(sz1, "w:val", "28");
		mxml_node_t* szcs1 = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(szcs1, "w:val", "28");
	}
	else if((strcmp(lvl, "2")==0) && (strcmp(numid, "2")==0))
	{
		mxml_node_t* sz = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(sz, "w:val", "22");
		mxml_node_t* szcs = mxmlNewElement(rpr, "w:sz");
		mxmlElementSetAttr(szcs, "w:val", "22");
	}	
	mxml_node_t* t = mxmlNewElement(r, "w:t");
	mxmlNewText(t, 0, heading);
	
}


//call lib
mxml_node_t*  createnumsection(mxml_node_t* body, char* lvl, char* numid)
{
	mxml_node_t* p = mxmlNewElement(body, "w:p");
	mxml_node_t* ppr = mxmlNewElement(p, "w:pPr");
	mxml_node_t* pstyle = mxmlNewElement(ppr,"w:pStyle");
	mxmlElementSetAttr(pstyle, "w:val", "style_");
	mxml_node_t* pnumpr = mxmlNewElement(ppr, "w:numPr");
	mxml_node_t* ilvl = mxmlNewElement(pnumpr, "w:ilvl");
	mxmlElementSetAttr(ilvl, "w:val", lvl);
	mxml_node_t* numid_ = mxmlNewElement(pnumpr, "w:numId");
	mxmlElementSetAttr(numid_, "w:val", numid);
	return p;
}



