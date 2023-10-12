<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Symfony\Component\HttpFoundation\Request;

class UploadInstructions extends DefaultPlugin
{
  const NAME = "upload_instructions";

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload Instructions"),
        self::MENU_LIST => "Upload::Instructions",
        self::PERMISSION => Auth::PERM_WRITE
      ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $vars['URI'] = Traceback_uri();

    return $this->render('upload_instructions.html.twig', $this->mergeWithDefault($vars));
  }

  private function asciiUnrock()
  {
    $V= '';
    $V .= "<P />\n";
    $V .= _("Select the type of upload based on where the data is located:\n");
    /* ASCII ART ROCKS! */
    $V .= "<table border=0>\n";
    $V .= "<tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $text = _("Your computer");
    $V .= "<td bgcolor='white' align='center'><a href='{$Uri}?mod=upload_file'>$text</a></td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'> &rarr; </td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $text = _("FOSSology web server");
    $V .= "<td bgcolor='white' align='center'><a href='{$Uri}?mod=upload_srv_files'>$text</a></td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white' align='center'>&darr;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $text = _("Remote web or FTP server");
    $V .= "<td bgcolor='white' align='center'><a href='{$Uri}?mod=upload_url'>$text</a></td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr><tr>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'>&nbsp;</td>";
    $V .= "</tr>";
    $V .= "</table>\n";
    return $V;
  }
}

register_plugin(new UploadInstructions());
