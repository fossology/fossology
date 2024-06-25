<?php

/**
 * SPDX-FileCopyrightText: © 2023 Akash Kumar Sah <akashsah2003@gmail.com>
 * SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\LicenseDao;

class Node
{
    public $type;
    public $value;
    public $left;
    public $right;

  public function __construct($type, $value)
  {
      $this->type = $type;
      $this->value = $value;
      $this->left = null;
      $this->right = null;
  }
}

class LicenseExpressionParser
{
    private $expression;
    private $pos;
    private $root;
    private $licenses;
    private $groupId;
    private $userId;
    private $licenseDao;

  public function __construct($expr, $groupId, $userId)
  {
      $this->licenseDao = $GLOBALS['container']->get('dao.license');
      $this->expression = $expr;
      $this->groupId = $groupId;
      $this->userId = $userId;
      $this->pos = 0;
      $this->root = null;
      $this->licenses = [];
  }

  public function parse()
  {
      $this->root = null;
      $operators = [];
      $operands = [];

    while ($this->pos < strlen($this->expression)) {
        $this->skipWhitespace();
      if (ctype_alpha($this->peek())) {
        if ($this->isOperatorAtCurrentPos()) {
            $op = $this->parseOperator();
          while (!empty($operators) && end($operators) !== "(" && $this->getPrecedence(end($operators)) >= $this->getPrecedence($op)) {
            if (!$this->applyOperator($operators, $operands)) {
                  return false;
            }
          }
            $operators[] = $op;
        } else {
            $licenseNode = null;
          if (!$this->parseLicense($licenseNode)) {
                return false;
          }
            $operands[] = $licenseNode;
        }
      } elseif ($this->peek() === '(') {
            $operators[] = "(";
            $this->advance();
      } elseif ($this->peek() === ')') {
        while (!empty($operators) && end($operators) !== "(") {
          if (!$this->applyOperator($operators, $operands)) {
              return false;
          }
        }
        if (empty($operators)) {
            return false; // Mismatched parentheses
        }
          array_pop($operators); // Pop the '('
          $this->advance();
      } else {
          return false; // Invalid character
      }
    }

    while (!empty($operators)) {
      if (!$this->applyOperator($operators, $operands)) {
        return false;
      }
    }

    if (count($operands) !== 1) {
        return false;
    }
      $this->root = array_pop($operands);
      return true;
  }

  public function getAST()
  {
    if ($this->root) {
        return $this->nodeToJson($this->root);
    }

      // If root is null, return an AST with all license names combined with "AND"
    if (!empty($this->licenses)) {
        $newRoot = new Node("Expression", "AND");
        $current = $newRoot;

        $it = 0;
        $licenseCount = count($this->licenses);
      foreach ($this->licenses as $license) {
          $current->left = new Node("License", $license);
          $it++;
        if ($it < $licenseCount) {
          $current->right = new Node("Expression", "AND");
          $current = $current->right;
        }
      }

        $this->root = $newRoot;
        return $this->nodeToJson($this->root);
    }

      return null;
  }

  private function isValidLicense($license)
  {
      return !$this->isOperator($license) && strlen($license) > 1;
  }

  private function isOperator($token)
  {
      return in_array($token, ["OR", "AND", "WITH"]);
  }

  private function getPrecedence($op)
  {
    switch ($op) {
      case "WITH":
        return 3;
      case "AND":
        return 2;
      case "OR":
        return 1;
      default:
        return 0;
    }
  }

  private function parseLicense(&$node)
  {
      $this->skipWhitespace();
      $start = $this->pos;
    while (ctype_alnum($this->peek()) || $this->peek() === '-' || $this->peek() === '.') {
        $this->advance();
    }
      $license = substr($this->expression, $start, $this->pos - $start);
    if ($this->isValidLicense($license)) {
      if ($this->licenseDao->getLicenseByShortName($license, $this->groupId) != null) {
          $license = $this->licenseDao->getLicenseByShortName($license, $this->groupId)->getId();
      } else {
          $license = $this->licenseDao->insertUploadLicense($license, "User Decision", $this->groupId, $this->userId);
      }
        $node = new Node("License", $license);
        $this->licenses[] = $license;
        return true;
    }
      return false;
  }

  private function parseOperator()
  {
      $this->skipWhitespace();
    if (strncasecmp(substr($this->expression, $this->pos, 2), "OR", 2) === 0) {
        $this->pos += 2;
        return "OR";
    } elseif (strncasecmp(substr($this->expression, $this->pos, 3), "AND", 3) === 0) {
        $this->pos += 3;
        return "AND";
    } elseif (strncasecmp(substr($this->expression, $this->pos, 4), "WITH", 4) === 0) {
        $this->pos += 4;
        return "WITH";
    }
      return "";
  }

  private function isOperatorAtCurrentPos()
  {
      return strncasecmp(substr($this->expression, $this->pos, 2), "OR", 2) === 0 ||
          strncasecmp(substr($this->expression, $this->pos, 3), "AND", 3) === 0 ||
          strncasecmp(substr($this->expression, $this->pos, 4), "WITH", 4) === 0;
  }

  private function skipWhitespace()
  {
    while ($this->pos < strlen($this->expression) && ctype_space($this->expression[$this->pos])) {
        $this->pos++;
    }
  }

  private function peek()
  {
      return $this->pos < strlen($this->expression) ? $this->expression[$this->pos] : '\0';
  }

  private function advance()
  {
    if ($this->pos < strlen($this->expression)) {
        $this->pos++;
    }
  }

  private function applyOperator(&$operators, &$operands)
  {
    if (count($operands) < 2) {
        return false;
    }
      $op = array_pop($operators);

      $right = array_pop($operands);
      $left = array_pop($operands);

      $newNode = new Node("Expression", $op);
      $newNode->left = $left;
      $newNode->right = $right;

      $operands[] = $newNode;
      return true;
  }

  private function nodeToJson($node)
  {
      $j = [];
      $j["type"] = $node->type;
      $j["value"] = $node->value;
    if ($node->left) {
        $j["left"] = $this->nodeToJson($node->left);
    }
    if ($node->right) {
        $j["right"] = $this->nodeToJson($node->right);
    }
      return $j;
  }
}
