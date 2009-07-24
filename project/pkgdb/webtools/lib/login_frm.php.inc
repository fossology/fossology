<?php 
include_once "lib/constants.php";
?>

         <table width=375 border="0" cellspacing="0" cellpadding="0" bgcolor="#DDDDDD">
         <tr>
                   <td colspan="3" valign="top" class="ModuleHeader">log on</td>
         </tr>
         <tr> <td colspan="3">&nbsp;</td> </tr>
         <tr>
            <td class="ModuleText">&nbsp;</td>
            <td class="ModuleText" valign="top"> <span id="header">
                <strong>E-Mail Address:</strong></span> </td>
            <td class="ModuleText">&nbsp;</td>
         </tr>
         <tr>
            <td class="ModuleText">&nbsp;</td>
            <td class="ModuleText" valign="top">
                <input type="text" name="user" maxlength="75" size="45" >
            </td>
            <td class="ModuleText">&nbsp;</td>
         </tr>
         <tr>
            <td class="ModuleText">&nbsp;</td>
            <td class="ModuleText" valign="top">
                Format: sally.smith@hp.com<br>
                     &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                     john_doe@hp.com </td>
            <td class="ModuleText">&nbsp;</td>
         </tr>
         <tr> <td colspan="3">&nbsp;</td> </tr>
         <tr>
            <td class="ModuleText">&nbsp;</td>
            <td class="ModuleText" valign="top"> <span id="header">
                     <strong>NT Account Password:</strong></span> </td>
            <td class="ModuleText">&nbsp;</td>
         </tr>
         <tr>
            <td class="ModuleText">&nbsp;</td>
            <td class="ModuleText" valign="top">
                      <input type="password" name="pass" size="45" maxlength="75" ">
            </td>
            <td class="ModuleText">&nbsp;</td>
         </tr>
         <tr> <td colspan="3">&nbsp;</td> </tr>
<tr>
   <td colspan=3 align="middle">
       <input type=image name="login" 
       src=<?php echo "\"http://linux.corp.hp.com/webtools/images/logon_btn.gif\"";?> >
   </td>
</tr>

         <tr> <td colspan="3">&nbsp;</td> </tr>
         <tr>
            <td colspan="3">
               <script language="javascript">
               <!--
                   drawFooter();
               //-->
               </script>
           </td>
       </tr>
       </table>
