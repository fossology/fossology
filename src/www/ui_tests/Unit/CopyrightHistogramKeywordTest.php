<?php
/*
 SPDX-FileCopyrightText: © 2026 DenishShiroya22 <denishshiroya22@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Agent\Copyright\UI\KeywordHintProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real AJAX row formatter without a database.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CopyrightHistogramKeywordTest extends TestCase
{
  /** @var CopyrightHistogramProcessPost */
  private $histogram;
  /** @var string */
  private $configPath;

  protected function setUp(): void
  {
    $srcRoot = dirname(__DIR__, 3);
    require_once $srcRoot . '/lib/php/Plugin/FO_Plugin.php';
    require_once $srcRoot . '/lib/php/common-plugin.php';
    require_once $srcRoot . '/lib/php/common-parm.php';
    require_once $srcRoot . '/lib/php/common-string.php';
    $GLOBALS['container'] = new class {
      public function get($name)
      {
        return new \stdClass();
      }
    };
    require_once $srcRoot . '/copyright/ui/ajax-copyright-hist.php';
    $this->histogram = (new \ReflectionClass('CopyrightHistogramProcessPost'))
      ->newInstanceWithoutConstructor();
    $this->configPath = tempnam(sys_get_temp_dir(), 'keyword-hist-');
    file_put_contents($this->configPath, json_encode(array(
      'groups' => array(
        array('keywords' => array('patent'), 'text' => '<script>alert("x")</script> & review')
      )
    )));
    $_SERVER['REQUEST_URI'] = '/repo/';
  }

  protected function tearDown(): void
  {
    unlink($this->configPath);
  }

  private function formatRow(string $type, bool $activated, bool $editable,
    ?KeywordHintProvider $hints = null): array
  {
    $method = new \ReflectionMethod('CopyrightHistogramProcessPost', 'fillTableRow');
    $method->setAccessible(true);
    return $method->invoke($this->histogram, array(
      'hash' => 'abc123', 'content' => 'patent', 'copyright_count' => 2
    ), 11, 7, '3', $type, 'copyright-list', '', $activated, $editable, $hints);
  }

  /**
   * @dataProvider keywordPermissions
   */
  public function testKeywordHintsAreEscapedAndControlsStayAligned(bool $activated,
    bool $editable): void
  {
    $row = $this->formatRow('keyword', $activated, $editable,
      new KeywordHintProvider($this->configPath));
    $this->assertSame(array('DT_RowId', 'DT_RowClass', 0, 1, 2, 3, 4), array_keys($row));
    $this->assertSame('7,11,abc123,keyword', $row['DT_RowId']);
    $this->assertSame('patent', $row[1]);
    $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; review', $row[2]);
    if ($editable) {
      $this->assertStringContainsString("id='deletekeywordabc123'", $row[3]);
      $this->assertStringContainsString("id='updatekeywordabc123'", $row[3]);
    } else {
      $this->assertSame($activated ? '' : 'deactivated', $row[3]);
    }
    $checkbox = $editable && $activated ? 'deleteBySelect' : 'undoBySelect';
    $this->assertStringContainsString("class='{$checkbox}keyword'", $row[4]);
    $this->assertStringContainsString("value='7,11,abc123,keyword'", $row[4]);
  }

  public function keywordPermissions(): array
  {
    return array(
      'active editable' => array(true, true),
      'inactive editable' => array(false, true),
      'active read only' => array(true, false),
      'inactive read only' => array(false, false)
    );
  }

  public function testMissingHintsKeepKeywordColumnAndActions(): void
  {
    $row = $this->formatRow('keyword', true, true);
    $this->assertSame('', $row[2]);
    $this->assertStringContainsString("id='deletekeywordabc123'", $row[3]);
    $this->assertStringContainsString("class='deleteBySelectkeyword'", $row[4]);
  }

  /**
   * @dataProvider otherFindingTypes
   */
  public function testOtherFindingTablesKeepTheirExistingLayout(string $type): void
  {
    $row = $this->formatRow($type, true, true, new KeywordHintProvider($this->configPath));
    $this->assertSame(array('DT_RowId', 'DT_RowClass', 0, 1, 2, 3), array_keys($row));
    $this->assertSame('patent', $row[1]);
    $this->assertStringContainsString("id='delete{$type}abc123'", $row[2]);
    $this->assertStringContainsString("class='deleteBySelect$type'", $row[3]);
  }

  public function otherFindingTypes(): array
  {
    return array(
      array('statement'), array('email'), array('url'), array('author'),
      array('ecc'), array('ipra'), array('scancode_statement')
    );
  }
}
