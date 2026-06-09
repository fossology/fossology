<?php

/*
 SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

class LicenseExpressionParser
{
  const TOKEN_END = 'END';
  const TOKEN_IDENTIFIER = 'IDENTIFIER';
  const TOKEN_AND = 'AND';
  const TOKEN_OR = 'OR';
  const TOKEN_WITH = 'WITH';
  const TOKEN_LPAREN = 'LPAREN';
  const TOKEN_RPAREN = 'RPAREN';
  const TOKEN_INVALID = 'INVALID';

  private $expression;
  private $pos;
  private $currentToken;
  private $root;
  private $contractAst;
  private $canonical;
  private $errorCode;
  private $groupId;
  private $userId;
  private $licenseDao;

  private static $canonicalLicenseIds = array(
    'apache-2.0' => 'Apache-2.0',
    'bsd-2-clause' => 'BSD-2-Clause',
    'bsd-3-clause' => 'BSD-3-Clause',
    'cc0-1.0' => 'CC0-1.0',
    'gpl-2.0' => 'GPL-2.0',
    'gpl-2.0-only' => 'GPL-2.0-only',
    'gpl-2.0-or-later' => 'GPL-2.0-or-later',
    'gpl-3.0-only' => 'GPL-3.0-only',
    'gpl-3.0-or-later' => 'GPL-3.0-or-later',
    'isc' => 'ISC',
    'lgpl-2.1+' => 'LGPL-2.1+',
    'lgpl-2.1-or-later' => 'LGPL-2.1-or-later',
    'mit' => 'MIT',
    'mpl-1.1+' => 'MPL-1.1+',
    'mpl-2.0' => 'MPL-2.0',
    'mpl-2.0-no-copyleft-exception' => 'MPL-2.0-no-copyleft-exception',
    'x11' => 'X11'
  );

  private static $canonicalExceptionIds = array(
    'autoconf-exception-2.0' => 'Autoconf-exception-2.0',
    'autoconf-exception-3.0' => 'Autoconf-exception-3.0',
    'bison-exception-2.2' => 'Bison-exception-2.2',
    'classpath-exception-2.0' => 'Classpath-exception-2.0',
    'gcc-exception-3.1' => 'GCC-exception-3.1',
    'llvm-exception' => 'LLVM-exception'
  );

  public function __construct($expr, $groupId, $userId)
  {
    $this->expression = $expr;
    $this->groupId = $groupId;
    $this->userId = $userId;
    $this->licenseDao = null;
    if (isset($GLOBALS['container'])) {
      $this->licenseDao = $GLOBALS['container']->get('dao.license');
    }
    $this->reset();
  }

  public function parse()
  {
    $this->reset();
    if (trim($this->expression) === '') {
      $this->errorCode = 'empty_expression';
      return false;
    }

    $this->nextToken();
    $root = $this->parseExpression();
    if ($root === null) {
      return false;
    }
    if ($this->currentToken['type'] !== self::TOKEN_END) {
      $this->errorCode = $this->currentToken['type'] === self::TOKEN_RPAREN ?
        'unexpected_closing_parenthesis' : 'unexpected_token';
      return false;
    }
    if ($this->containsSpecial($root) && $root['type'] !== 'special') {
      $this->errorCode = 'special_license_must_stand_alone';
      return false;
    }

    $this->root = $root;
    $this->contractAst = $this->nodeToContractAst($root);
    $this->canonical = $this->nodeToCanonical($root, 0);
    return true;
  }

  public function getAST()
  {
    if ($this->contractAst === null && !$this->parse()) {
      return null;
    }
    return $this->contractAstToStoredAst($this->contractAst);
  }

  public function getContractAST()
  {
    if ($this->contractAst === null && !$this->parse()) {
      return null;
    }
    return $this->contractAst;
  }

  public function getCanonical()
  {
    if ($this->canonical === null && !$this->parse()) {
      return null;
    }
    return $this->canonical;
  }

  public function getErrorCode()
  {
    return $this->errorCode;
  }

  private function reset()
  {
    $this->pos = 0;
    $this->currentToken = array('type' => self::TOKEN_END, 'text' => '');
    $this->root = null;
    $this->contractAst = null;
    $this->canonical = null;
    $this->errorCode = null;
  }

  private function nextToken()
  {
    $length = strlen($this->expression);
    while ($this->pos < $length && ctype_space($this->expression[$this->pos])) {
      $this->pos++;
    }

    if ($this->pos >= $length) {
      $this->currentToken = array('type' => self::TOKEN_END, 'text' => '');
      return;
    }

    $ch = $this->expression[$this->pos];
    if ($ch === '(') {
      $this->pos++;
      $this->currentToken = array('type' => self::TOKEN_LPAREN, 'text' => '(');
      return;
    }
    if ($ch === ')') {
      $this->pos++;
      $this->currentToken = array('type' => self::TOKEN_RPAREN, 'text' => ')');
      return;
    }

    $start = $this->pos;
    while ($this->pos < $length &&
      !ctype_space($this->expression[$this->pos]) &&
      $this->expression[$this->pos] !== '(' &&
      $this->expression[$this->pos] !== ')') {
      $this->pos++;
    }

    $text = substr($this->expression, $start, $this->pos - $start);
    if (strcasecmp($text, 'AND') === 0) {
      $this->currentToken = array('type' => self::TOKEN_AND, 'text' => $text);
    } elseif (strcasecmp($text, 'OR') === 0) {
      $this->currentToken = array('type' => self::TOKEN_OR, 'text' => $text);
    } elseif (strcasecmp($text, 'WITH') === 0) {
      $this->currentToken = array('type' => self::TOKEN_WITH, 'text' => $text);
    } elseif ($this->identifierHasValidChars($text)) {
      $this->currentToken = array('type' => self::TOKEN_IDENTIFIER, 'text' => $text);
    } else {
      $this->currentToken = array('type' => self::TOKEN_INVALID, 'text' => $text);
      $this->errorCode = 'invalid_token';
    }
  }

  private function identifierHasValidChars($text)
  {
    return $text !== '' && preg_match('/^[A-Za-z0-9.+:-]+$/', $text) === 1;
  }

  private function parseExpression()
  {
    $left = $this->parseAndExpression();
    while ($left !== null && $this->currentToken['type'] === self::TOKEN_OR) {
      $this->nextToken();
      $right = $this->parseAndExpression();
      if ($right === null) {
        return null;
      }
      $left = array('type' => 'OR', 'left' => $left, 'right' => $right);
    }
    return $left;
  }

  private function parseAndExpression()
  {
    $left = $this->parseWithExpression();
    while ($left !== null && $this->currentToken['type'] === self::TOKEN_AND) {
      $this->nextToken();
      $right = $this->parseWithExpression();
      if ($right === null) {
        return null;
      }
      $left = array('type' => 'AND', 'left' => $left, 'right' => $right);
    }
    return $left;
  }

  private function parseWithExpression()
  {
    $left = $this->parsePrimary();
    if ($left === null) {
      return null;
    }
    if ($this->currentToken['type'] !== self::TOKEN_WITH) {
      return $left;
    }
    if (!$this->nodeCanHaveException($left)) {
      $this->errorCode = 'with_requires_simple_license';
      return null;
    }

    $this->nextToken();
    if ($this->currentToken['type'] !== self::TOKEN_IDENTIFIER) {
      $this->errorCode = 'expected_exception';
      return null;
    }
    $exception = array(
      'type' => 'exception',
      'id' => $this->canonicalIdentifier(
        $this->currentToken['text'], self::$canonicalExceptionIds)
    );
    $this->nextToken();

    return array('type' => 'WITH', 'license' => $left, 'exception' => $exception);
  }

  private function parsePrimary()
  {
    if ($this->currentToken['type'] === self::TOKEN_LPAREN) {
      $this->nextToken();
      $node = $this->parseExpression();
      if ($node === null) {
        return null;
      }
      if ($this->currentToken['type'] !== self::TOKEN_RPAREN) {
        $this->errorCode = 'missing_closing_parenthesis';
        return null;
      }
      $this->nextToken();
      return $node;
    }

    if ($this->currentToken['type'] !== self::TOKEN_IDENTIFIER) {
      if ($this->errorCode === null) {
        $this->errorCode = 'expected_license';
      }
      return null;
    }

    $value = $this->currentToken['text'];
    $this->nextToken();

    if ($this->isSpecialValue($value)) {
      return array('type' => 'special', 'id' => strtoupper($value));
    }
    if ($this->isLicenseRef($value)) {
      return array('type' => 'licenseRef', 'id' => $value);
    }
    return array(
      'type' => 'license',
      'id' => $this->canonicalIdentifier($value, self::$canonicalLicenseIds)
    );
  }

  private function isSpecialValue($value)
  {
    return strcasecmp($value, 'NONE') === 0 || strcasecmp($value, 'NOASSERTION') === 0;
  }

  private function isLicenseRef($value)
  {
    return strpos($value, 'LicenseRef-') === 0 ||
      strpos($value, ':LicenseRef-') !== false;
  }

  private function canonicalIdentifier($value, $identifiers)
  {
    $key = strtolower($value);
    return array_key_exists($key, $identifiers) ? $identifiers[$key] : $value;
  }

  private function nodeCanHaveException($node)
  {
    return $node['type'] === 'license' || $node['type'] === 'licenseRef';
  }

  private function containsSpecial($node)
  {
    if ($node['type'] === 'special') {
      return true;
    }
    if ($node['type'] === 'WITH') {
      return $this->containsSpecial($node['license']) ||
        $this->containsSpecial($node['exception']);
    }
    if ($node['type'] === 'AND' || $node['type'] === 'OR') {
      return $this->containsSpecial($node['left']) ||
        $this->containsSpecial($node['right']);
    }
    return false;
  }

  private function nodeToContractAst($node)
  {
    switch ($node['type']) {
      case 'license':
      case 'licenseRef':
      case 'exception':
      case 'special':
        return array('type' => $node['type'], 'id' => $node['id']);
      case 'WITH':
        return array(
          'type' => 'WITH',
          'license' => $this->nodeToContractAst($node['license']),
          'exception' => $this->nodeToContractAst($node['exception'])
        );
      case 'AND':
      case 'OR':
        return array(
          'type' => $node['type'],
          'left' => $this->nodeToContractAst($node['left']),
          'right' => $this->nodeToContractAst($node['right'])
        );
    }
    return null;
  }

  private function nodeToCanonical($node, $parentPrecedence)
  {
    $precedence = $this->precedenceForNode($node);
    $wrap = $precedence < $parentPrecedence;
    $text = '';

    switch ($node['type']) {
      case 'license':
      case 'licenseRef':
      case 'exception':
      case 'special':
        $text = $node['id'];
        break;
      case 'WITH':
        $text = $this->nodeToCanonical($node['license'], $precedence) .
          ' WITH ' . $this->nodeToCanonical($node['exception'], $precedence);
        break;
      case 'AND':
      case 'OR':
        $text = $this->nodeToCanonical($node['left'], $precedence) .
          ' ' . $node['type'] . ' ' .
          $this->nodeToCanonical($node['right'], $precedence);
        break;
    }

    return $wrap ? '(' . $text . ')' : $text;
  }

  private function precedenceForNode($node)
  {
    switch ($node['type']) {
      case 'OR':
        return 1;
      case 'AND':
        return 2;
      case 'WITH':
        return 3;
      default:
        return 4;
    }
  }

  private function contractAstToStoredAst($node)
  {
    $type = $node['type'];
    if ($type === 'license' || $type === 'licenseRef' ||
      $type === 'exception' || $type === 'special') {
      return array(
        'type' => 'License',
        'value' => $this->resolveLicenseId($node['id'])
      );
    }

    $stored = array('type' => 'Expression', 'value' => $type);
    if ($type === 'WITH') {
      $stored['left'] = $this->contractAstToStoredAst($node['license']);
      $stored['right'] = $this->contractAstToStoredAst($node['exception']);
    } else {
      $stored['left'] = $this->contractAstToStoredAst($node['left']);
      $stored['right'] = $this->contractAstToStoredAst($node['right']);
    }
    return $stored;
  }

  private function resolveLicenseId($license)
  {
    $licenseDao = $this->getLicenseDao();
    $licenseRef = $licenseDao->getLicenseByShortName($license, $this->groupId);
    if ($licenseRef !== null) {
      return $licenseRef->getId();
    }

    $licenseRef = $licenseDao->getLicenseBySpdxId($license, $this->groupId);
    if ($licenseRef !== null) {
      return $licenseRef->getId();
    }

    return intval($licenseDao->insertUploadLicense(
      $license, 'User Decision', $this->groupId, $this->userId));
  }

  private function getLicenseDao()
  {
    if ($this->licenseDao === null) {
      $this->licenseDao = $GLOBALS['container']->get('dao.license');
    }
    return $this->licenseDao;
  }
}
