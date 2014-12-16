<?php
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

namespace Fossology\Monk\UI;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OneShot extends DefaultPlugin
{
  const NAME = "oneshot-monk";

  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var HighlightRenderer */
  private $highlightRenderer;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "One-Shot Monk",
        self::MENU_LIST => "Main::Upload::One-Shot Monk",
        self::REQUIRES_LOGIN => false,
        self::DEPENDENCIES => array(\ui_menu::NAME)
    ));

    $this->highlightProcessor = $this->getObject('view.highlight_processor');
    $this->highlightRenderer = $this->getObject('view.highlight_renderer');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $vars = array(
        'content' => ''
    );

    return $this->render('base.html.twig', $this->mergeWithDefault($vars));
  }


  public function scanMonk($text)
  {
    $tmpfname = tempnam("/tmp", "monk");
    $handle = fopen($tmpfname, "w");
    fwrite($handle, $text);
    fclose($handle);

    global $SYSCONFDIR;
    $cmd = dirname(__DIR__).'/agent/monk -c '.$SYSCONFDIR.' '.$tmpfname;
    exec($cmd, $output, $return_var);
    unlink($tmpfname);

    $this->highlightProcessor->calculateSplitPositions();
    return ;
  }
}

register_plugin(new OneShot());
