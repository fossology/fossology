<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Monk\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\TextFragment;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\View\TextRenderer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OneShot extends DefaultPlugin
{
  const NAME = "oneshot-monk";

  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var HighlightRenderer */
  private $highlightRenderer;
  /** @var TextRenderer */
  private $textRenderer;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "One-Shot Monk",
        self::MENU_LIST => "Upload::One-Shot Monk Analysis",
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => true
    ));

    $this->highlightProcessor = $this->getObject('view.highlight_processor');
    $this->highlightRenderer = $this->getObject('view.highlight_renderer');
    $this->textRenderer = $this->getObject('view.text_renderer');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    /** @var UploadedFile */
    $uploadFile = $request->files->get('file_input');
    if ($uploadFile === null) {
      return $this->render('oneshot-upload.html.twig', $this->getDefaultVars());
    }
    $fullpath = $uploadFile->getPath().'/'.$uploadFile->getFilename();

    list($licenseIds, $rendered) = $this->scanMonkFileRendered($fullpath);
    $closeButton = "<button id='closeAnalysis' style='margin: 10px; padding: 5px;'>Close</button>";
    $vars = $this->mergeWithDefault(array(
      'content' => $closeButton . $this->renderLicenseList($licenseIds) . $rendered
    ));
    $vars['styles'] .= "<link rel='stylesheet' href='css/highlights.css'>\n";
    $vars['scripts'] .= "
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const closeButton = document.getElementById('closeAnalysis');
          let abortController = new AbortController();
          const existingFetch = window.fetch;
          window.fetch = function(url, options = {}) {
            options.signal = abortController.signal;
            return existingFetch(url, options);
 };
          closeButton.addEventListener('click', function() {
            abortController.abort();
            window.location.href = '?mod=oneshot-monk';
          });
        });
      </script>
    ";
    return $this->render('include/base.html.twig', $vars);
  }

  public function scanMonkRendered($text, $fromRest = false)
  {
    $tmpFileName = tempnam("/tmp", "monk");
    if (!$tmpFileName) {
      throw new \Exception("cannot create temporary file");
    }
    $handle = fopen($tmpFileName, "w");
    fwrite($handle, $text);
    fclose($handle);
    list($licenseIds, $highlights) = $this->scanMonk($tmpFileName);
    unlink($tmpFileName);

    $this->highlightProcessor->addReferenceTexts($highlights);
    if ($fromRest) {
      return array($licenseIds, $highlights);
    }

    $splitPositions = $this->highlightProcessor->calculateSplitPositions($highlights);
    $textFragment = new TextFragment(0, $text);

    $rendered = $this->textRenderer->renderText($textFragment, $splitPositions);
    return array($licenseIds, $rendered);
  }

  public function scanMonkFileRendered($tmpfname)
  {
    list($licenseIds, $highlights) = $this->scanMonk($tmpfname);

    $text = file_get_contents($tmpfname);

    $this->highlightProcessor->addReferenceTexts($highlights);
    $splitPositions = $this->highlightProcessor->calculateSplitPositions($highlights);
    $textFragment = new TextFragment(0, $text);

    $rendered = $this->textRenderer->renderText($textFragment, $splitPositions);

    return array($licenseIds, $rendered);
  }

  public function scanMonk($fileName)
  {
    global $SYSCONFDIR;
    $cmd = dirname(__DIR__).'/agent/monk -c '.$SYSCONFDIR.' '.$fileName;
    exec($cmd, $output, $returnVar);
    if ($returnVar != 0) {
      throw new \Exception("scan failed with $returnVar");
    }

    $qFileName = preg_quote($fileName, "/");
    $licenseIds = array();
    $highlights = array();
    foreach ($output as $line) {
      $lineMatches = array();
      if (preg_match('/found diff match between "'.$qFileName.'" and "[^"]*" \(rf_pk=(?P<rf>[0-9]+)\); rank (?P<rank>[0-9]{1,3}); diffs: \{(?P<diff>[st\[\]0-9, MR+-]+)}/', $line, $lineMatches)) {
        $licenseId = $lineMatches['rf'];
        $licenseIds[] = $licenseId;
        $this->addDiffsToHighlights($licenseId, $lineMatches, $highlights);
      }
      if (preg_match('/found full match between "'.$qFileName.'" and "[^"]*" \(rf_pk=(?P<rf>[0-9]+)\); matched: (?P<start>[0-9]*)\+?(?P<len>[0-9]*)?/', $line, $lineMatches)) {
        $licenseId = $lineMatches['rf'];
        $licenseIds[] = $licenseId;

        $start = $lineMatches['start'];
        $end = $start + $lineMatches['len'];

        $type = Highlight::MATCH;

        $highlight = new Highlight($start, $end, $type);
        $highlight->setLicenseId($licenseId);

        $highlights[] = $highlight;
      }
    }

    return array($licenseIds, $highlights);
  }

  private function addDiffsToHighlights($licenseId, $lineMatches, &$highlights)
  {
    foreach (explode(',', $lineMatches['diff']) as $diff) {
      if (preg_match('/t\[(?P<start>[0-9]*)\+?(?P<len>[0-9]*)?\] M(?P<type>.?) s\[(?P<rf_start>[0-9]*)\+?(?P<rf_len>[0-9]*)?\]/', $diff, $diffMatches)) {
        $start = intval($diffMatches['start']);
        $end = $start + intval($diffMatches['len']);
        $rfStart = intval($diffMatches['rf_start']);
        $rfEnd = $rfStart + intval($diffMatches['rf_len']);

        switch ($diffMatches['type']) {
          case '0':
            $type = Highlight::MATCH;
            break;
          case 'R':
            $type = Highlight::CHANGED;
            break;
          case '-':
            $type = Highlight::DELETED;
            break;
          case '+':
            $type = Highlight::ADDED;
            break;
          default:
            throw new \Exception('unrecognized diff type');
        }
        $highlight = new Highlight($start, $end, $type, $rfStart, $rfEnd);
        $highlight->setLicenseId($licenseId);

        $highlights[] = $highlight;
      } else {
        throw new \Exception('failed parsing diff element: '.$diff);
      }
    }
  }

  public function renderLicenseList($licenseIds)
  {
    $content = '';
    global $container;
    /** @var LicenseDao $licenseDao */
    $licenseDao = $container->get('dao.license');
    $isLoggedIn = $this->isLoggedIn();
    foreach ($licenseIds as $licenseId) {
      /** @var License */
      $license = $licenseDao->getLicenseById($licenseId);
      if ($isLoggedIn) {
        $js = "javascript:window.open('?mod=popup-license&rf=" . $license->getId() . "','License text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');";
        $content .= '<li><a onclick="' . $js . '" href="javascript:;">' . $license->getShortName() . '</a></li>';
      } else {
        $content .= '<li>' . $license->getShortName() . '</li>';
      }
    }
    return $content ? _('Possible licenses').":<ul>$content</ul>" : _('No match found').'<hr/>';
  }
}

register_plugin(new OneShot());
