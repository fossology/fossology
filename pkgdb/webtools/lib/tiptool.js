//<!-- 
//************************  README  *************************************
// STEP 1: include this file in the <HEAD> section:
//         <script language="javascript" type="text/javascript" src="tiptool.js"></script>
//
// STEP 2: include this in the BODY section.  Just before </body> is fine.
//         <div id="tipDiv" style="position:absolute; visibility:hidden; z-index:100"></div>
//
// STEP 3: define your mouseover.
//         $tip = "onmouseover=\"doTip(event,'http://myimage', 'mymessage')" onmouseout="hideTip()"
//         For example:
//         $tip = "onMouseOver=\"doTip(1, '', '$box_name')\"; onMouseOut=\"hideTip();\"";
//         print "<a href='url' $tip> linktext </a>

//
//From: http://simplythebest.net/scripts/dhtml_script_95.html
//Bob Gobeille, 2004:  modified to remove static message array (parameterize doTip)
//                     added README
//***********************************************************************
//-->


//<!--
// This code is from Dynamic Web Coding www.dyn-web.com 
// Copyright 2002 by Sharon Paine Permission granted to use this code as long as this entire notice is included.
// Permission granted to SimplytheBest.net to feature script in its
// DHTML script collection at http://simplythebest.net/scripts/dhtml_scripts.html

var dom = (document.getElementById) ? true : false;
var ns5 = ((navigator.userAgent.indexOf("Gecko")>-1) && dom) ? true: false;
var ie5 = ((navigator.userAgent.indexOf("MSIE")>-1) && dom) ? true : false;
var ns4 = (document.layers && !dom) ? true : false;
var ie4 = (document.all && !dom) ? true : false;
var nodyn = (!ns5 && !ns4 && !ie4 && !ie5) ? true : false;

var origWidth, origHeight;
if (ns4) {
    origWidth = window.innerWidth; origHeight = window.innerHeight;
    window.onresize = function() { if (window.innerWidth != origWidth || window.innerHeight != origHeight) history.go(0); }
}

if (nodyn) { event = "nope" }
var tipFollowMouse  = true; 
var tipWidth        = 160;
var offX            = 2;   // how far from mouse to show tip
var offY            = 2; 
var tipFontFamily   = "Verdana, arial, helvetica, sans-serif";
var tipFontSize     = "8pt";
var tipFontColor        = "#000000";
var tipBgColor      = "#DDECFF"; 
var origBgColor         = tipBgColor; // in case no bgColor set in array
var tipBorderColor  = "#000080";
var tipBorderWidth  = 2;
var tipBorderStyle  = "ridge";
var tipPadding      = 4;


var tooltip, tipcss;
function initTip() {
    if (nodyn) return;
    tooltip = (ns4)? document.tipDiv.document: (ie4)? document.all['tipDiv']: (ie5||ns5)? document.getElementById('tipDiv'): null;
    tipcss = (ns4)? document.tipDiv: tooltip.style;
    if (ie4||ie5||ns5) {    // ns4 would lose all this on rewrites
        tipcss.width = tipWidth+"px";
        tipcss.fontFamily = tipFontFamily;
        tipcss.fontSize = tipFontSize;
        tipcss.color = tipFontColor;
        tipcss.backgroundColor = tipBgColor;
        tipcss.borderColor = tipBorderColor;
        tipcss.borderWidth = tipBorderWidth+"px";
        tipcss.padding = tipPadding+"px";
        tipcss.borderStyle = tipBorderStyle;
    }
    if (tooltip&&tipFollowMouse) {
        if (ns4) document.captureEvents(Event.MOUSEMOVE);
        document.onmousemove = trackMouse;
    }
}

window.onload = initTip;

var t1,t2;  // for setTimeouts
var tipOn = false;  // check if over tooltip link
// img (optional) is the uri to the image to use, msg is a text message
function doTip(evt, img, msg) {

    if (!tooltip) return;
    if (t1) clearTimeout(t1);   if (t2) clearTimeout(t2);

    if (img) 
    {
        startStr = '<table width="' + tipWidth + '"><tr><td align="center" width="100%"><img src="' + img + '" border="0"></td></tr><tr><td valign="top">';
        endStr = '</td></tr></table>';
    } 
    else 
    {
        startStr = '<table width="' + tipWidth + '"><tr><td align="center" width="100%" border="0"></td></tr><tr><td valign="top">';
        endStr = '</td></tr></table>';
    }

    tipOn = true;
    curBgColor = tipBgColor;
    curFontColor = tipFontColor;
    if (ns4) {
        var tip = '<table bgcolor="' + tipBorderColor + '" width="' + tipWidth + '" cellspacing="0" cellpadding="' + tipBorderWidth + '" border="0"><tr><td><table bgcolor="' + curBgColor + '" width="100%" cellspacing="0" cellpadding="' + tipPadding + '" border="0"><tr><td>'+ startStr + '<span style="font-family:' + tipFontFamily + '; font-size:' + tipFontSize + '; color:' + curFontColor + ';">' + msg + '</span>' + endStr + '</td></tr></table></td></tr></table>';
        tooltip.write(tip);
        tooltip.close();
    } else if (ie4||ie5||ns5) {
        var tip = startStr + '<span style="font-family:' + tipFontFamily + '; font-size:' + tipFontSize + '; color:' + curFontColor + ';">' + msg + '</span>' + endStr;
        tipcss.backgroundColor = curBgColor;
        tooltip.innerHTML = tip;
    }
    if (!tipFollowMouse) positionTip(evt);
    else t1=setTimeout("tipcss.visibility='visible'",100);
}

var mouseX, mouseY;
function trackMouse(evt) {
    mouseX = (ns4||ns5)? evt.pageX: window.event.clientX + document.body.scrollLeft;
    mouseY = (ns4||ns5)? evt.pageY: window.event.clientY + document.body.scrollTop;
    if (tipOn) positionTip(evt);
}

function positionTip(evt) {
    if (!tipFollowMouse) {
        mouseX = (ns4||ns5)? evt.pageX: window.event.clientX + document.body.scrollLeft;
        mouseY = (ns4||ns5)? evt.pageY: window.event.clientY + document.body.scrollTop;
    }
    // tooltip width and height
    var tpWd = (ns4)? tooltip.width: (ie4||ie5)? tooltip.clientWidth: tooltip.offsetWidth;
    var tpHt = (ns4)? tooltip.height: (ie4||ie5)? tooltip.clientHeight: tooltip.offsetHeight;
    // document area in view (subtract scrollbar width for ns)
    var winWd = (ns4||ns5)? window.innerWidth-20+window.pageXOffset: document.body.clientWidth+document.body.scrollLeft;
    var winHt = (ns4||ns5)? window.innerHeight-20+window.pageYOffset: document.body.clientHeight+document.body.scrollTop;
    // check mouse position against tip and window dimensions
    // and position the tooltip 
    if ((mouseX+offX+tpWd)>winWd) 
        tipcss.left = (ns4)? mouseX-(tpWd+offX): mouseX-(tpWd+offX)+"px";
    else tipcss.left = (ns4)? mouseX+offX: mouseX+offX+"px";
    if ((mouseY+offY+tpHt)>winHt) 
        tipcss.top = (ns4)? winHt-(tpHt+offY): winHt-(tpHt+offY)+"px";
    else tipcss.top = (ns4)? mouseY+offY: mouseY+offY+"px";
    if (!tipFollowMouse) t1=setTimeout("tipcss.visibility='visible'",100);
}

function hideTip() {
    if (!tooltip) return;
    t2=setTimeout("tipcss.visibility='hidden'",100);
    tipOn = false;
}
//-->
//</script>
