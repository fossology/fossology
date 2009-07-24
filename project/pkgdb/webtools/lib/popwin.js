// popup message window
function openindex(evt, msg)
{
   OpenWindow=window.open("", "newwin", "height=150, width=250,toolbar=no,scrollbars="+scroll+",menubar=no, left=100 ");
   OpenWindow.document.write("<BODY >")
   OpenWindow.document.write(msg)
   OpenWindow.document.write("</BODY>")
   OpenWindow.document.write("</HTML>")
                                                                                
   OpenWindow.document.close()
   self.name="main"
}
