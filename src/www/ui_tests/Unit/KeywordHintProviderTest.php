<?php
/*
 SPDX-FileCopyrightText: © 2026 DenishShiroya22 <denishshiroya22@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Agent\Copyright\UI\KeywordHintProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/copyright/ui/KeywordHintProvider.php';

class KeywordHintProviderTest extends TestCase
{
  /** @var string */
  private $configPath;

  protected function setUp(): void
  {
    $this->configPath = tempnam(sys_get_temp_dir(), 'keyword-hints-');
  }

  protected function tearDown(): void
  {
    if (is_file($this->configPath)) {
      unlink($this->configPath);
    }
  }

  public function testGroupsAndMatching(): void
  {
    file_put_contents($this->configPath, json_encode(array(
      'groups' => array(
        array('keywords' => array('patent', 'patented'), 'text' => 'Check patents'),
        array('keywords' => array('licensed under'), 'text' => 'Check license'),
        array('keywords' => array('patent'), 'text' => 'Do not override')
      )
    )));
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('Check patents', $provider->getHint('patent'));
    $this->assertSame('Check patents', $provider->getHint('PATENTED'));
    $this->assertSame('Check license', $provider->getHint("licensed   \tunder"));
    $this->assertSame('', $provider->getHint('no matching guidance'));
    $this->assertSame('', $provider->getHint('patent pending'));
  }

  public function testMissingFileDoesNotBreakExistingFindings(): void
  {
    unlink($this->configPath);
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('', $provider->getHint('patent'));
  }

  public function testMalformedGroupsAreIgnored(): void
  {
    file_put_contents($this->configPath, json_encode(array(
      'groups' => array(
        null,
        array('keywords' => 'patent', 'text' => 'invalid'),
        array('keywords' => array('patent'), 'text' => 12),
        array('keywords' => array(12, '', '   ', null), 'text' => 'invalid'),
        array('keywords' => array('patent'), 'text' => '<script>alert(1)</script>')
      )
    )));
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('<script>alert(1)</script>', $provider->getHint('patent'));
    $this->assertSame('', $provider->getHint('not configured'));
    $this->assertSame('', $provider->getHint(''));
  }

  public function testInvalidUtf8IsNotMatched(): void
  {
    file_put_contents($this->configPath, '{"groups":[{"keywords":["patent"],"text":"review"}]}');
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('', $provider->getHint("\xFF"));
  }

  public function testUnicodeCaseAndSurroundingWhitespace(): void
  {
    file_put_contents($this->configPath, json_encode(array(
      'groups' => array(
        array('keywords' => array("\u{00A0}LICensed\nunder\u{2003}"), 'text' => 'Check license'),
        array('keywords' => array('ÜBER'), 'text' => 'Unicode guidance')
      )
    )));
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('Check license', $provider->getHint("\u{2003}licensed\tunder\u{00A0}"));
    $this->assertSame('Check license', $provider->getHint('licensed under'));
    $this->assertSame('Unicode guidance', $provider->getHint('über'));
  }

  /**
   * @dataProvider invalidConfigurations
   */
  public function testInvalidConfigurationDoesNotBreakFindings(string $configuration): void
  {
    file_put_contents($this->configPath, $configuration);
    $provider = new KeywordHintProvider($this->configPath);
    $this->assertSame('', $provider->getHint('patent'));
  }

  public function invalidConfigurations(): array
  {
    return array(
      'malformed JSON' => array('{'),
      'invalid encoding' => array("{\"groups\":[\"\xFF\"]}"),
      'null' => array('null'),
      'scalar' => array('"patent"'),
      'missing groups' => array('{}'),
      'invalid groups' => array('{"groups":"patent"}'),
      'empty groups' => array('{"groups":[]}')
    );
  }

  public function testEmptyTextCanSuppressLaterGroups(): void
  {
    file_put_contents($this->configPath, json_encode(array(
      'groups' => array(
        array('keywords' => array('patent'), 'text' => ''),
        array('keywords' => array('PATENT'), 'text' => 'Do not override')
      )
    )));
    $this->assertSame('', (new KeywordHintProvider($this->configPath))->getHint('patent'));
  }

  public function testConfigurationChangesApplyToNewRequests(): void
  {
    file_put_contents($this->configPath, '{"groups":[{"keywords":["patent"],"text":"First"}]}');
    $this->assertSame('First', (new KeywordHintProvider($this->configPath))->getHint('patent'));
    file_put_contents($this->configPath, '{"groups":[{"keywords":["patent"],"text":"Updated"}]}');
    $this->assertSame('Updated', (new KeywordHintProvider($this->configPath))->getHint('patent'));
  }

  public function testDefaultPathUsesEnabledKeywordModule(): void
  {
    $hadSysconfDir = array_key_exists('SYSCONFDIR', $GLOBALS);
    $originalSysconfDir = $GLOBALS['SYSCONFDIR'] ?? null;
    $siteRoot = $this->configPath . '-site';
    $agentDir = $siteRoot . '/mods-enabled/keyword/agent';
    mkdir($agentDir, 0700, true);
    $configPath = $agentDir . '/keyword-hints.json';
    file_put_contents($configPath, '{"groups":[{"keywords":["patent"],"text":"Installed guidance"}]}');
    try {
      $GLOBALS['SYSCONFDIR'] = $siteRoot . '/';
      $this->assertSame('Installed guidance', (new KeywordHintProvider())->getHint('patent'));
      $GLOBALS['SYSCONFDIR'] = '';
      $this->assertSame('', (new KeywordHintProvider())->getHint('patent'));
    } finally {
      if ($hadSysconfDir) {
        $GLOBALS['SYSCONFDIR'] = $originalSysconfDir;
      } else {
        unset($GLOBALS['SYSCONFDIR']);
      }
      unlink($configPath);
      rmdir($agentDir);
      rmdir(dirname($agentDir));
      rmdir(dirname($agentDir, 2));
      rmdir($siteRoot);
    }
  }
}
