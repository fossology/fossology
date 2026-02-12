<?php
/*
 SPDX-FileCopyrightText: © 2026 Contribution for GSoC
 
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

/**
 * Utility class to extract file includers/dependencies from source files
 */
class FileIncluderExtractor
{
  /**
   * Extract includers from file content based on file type
   * @param string $content File content
   * @param string $filename Filename to help detect type
   * @return array Array of includer information
   */
  public function extractIncluders($content, $filename)
  {
    if (empty($content)) {
      return [];
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $includers = [];

    // Figure out what kind of file this is and parse accordingly
    switch ($extension) {
      case 'c':
      case 'h':
      case 'cpp':
      case 'hpp':
      case 'cc':
        $includers = $this->parseCIncludes($content);
        break;

      case 'py':
        $includers = $this->parsePythonImports($content);
        break;

      case 'js':
      case 'ts':
        $includers = $this->parseJavaScriptRequires($content);
        break;

      case 'go':
        $includers = $this->parseGoImports($content);
        break;

      default:
        // Dockerfile won't have an extension, check the name
        $basename = basename($filename);
        if (strcasecmp($basename, 'Dockerfile') === 0) {
          $includers = $this->parseDockerfileFrom($content);
        }
        break;
    }

    return $includers;
  }

  /**
   * Parse C/C++ #include statements
   */
  private function parseCIncludes($content)
  {
    $includers = [];
    $lines = explode("\n", $content);

    foreach ($lines as $lineNum => $line) {
      // Look for #include statements (both <> and "" styles)
      if (preg_match('/^\s*#\s*include\s*[<"]([^>"]+)[>"]/', $line, $matches)) {
        $includers[] = [
          'type' => 'include',
          'value' => trim($matches[1]),
          'line' => $lineNum + 1
        ];
      }
    }

    return $includers;
  }

  /**
   * Parse Python import statements
   */
  private function parsePythonImports($content)
  {
    $includers = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $lineNum => $line) {
      $trimmed = trim($line);
      
      // Skip comments and empty lines
      if (empty($trimmed) || $trimmed[0] === '#') {
        continue;
      }
      
      // Match 'import module' or 'import module as alias'
      if (preg_match('/^import\s+([a-zA-Z0-9_., ]+)/', $trimmed, $matches)) {
        $modules = explode(',', $matches[1]);
        foreach ($modules as $module) {
          $module = trim($module);
          // Remove 'as' aliases
          $module = preg_replace('/\s+as\s+.*$/', '', $module);
          $includers[] = [
            'type' => 'import',
            'value' => trim($module),
            'line' => $lineNum + 1
          ];
        }
      }
      
      // Match 'from module import ...'
      if (preg_match('/^from\s+([a-zA-Z0-9_.]+)\s+import/', $trimmed, $matches)) {
        $includers[] = [
          'type' => 'from',
          'value' => trim($matches[1]),
          'line' => $lineNum + 1
        ];
      }
    }
    
    return $includers;
  }

  /**
   * Parse JavaScript/TypeScript require() and import statements
   */
  private function parseJavaScriptRequires($content)
  {
    $includers = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $lineNum => $line) {
      $trimmed = trim($line);
      
      // Match require('module') or require("module")
      if (preg_match('/require\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $trimmed, $matches)) {
        $includers[] = [
          'type' => 'require',
          'value' => $matches[1],
          'line' => $lineNum + 1
        ];
      }
      
      // Match ES6 import statements: import ... from 'module'
      if (preg_match('/import\s+.*from\s+[\'"]([^\'"]+)[\'"]/', $trimmed, $matches)) {
        $includers[] = [
          'type' => 'import',
          'value' => $matches[1],
          'line' => $lineNum + 1
        ];
      }
    }
    
    return $includers;
  }

  /**
   * Parse Go import statements
   */
  private function parseGoImports($content)
  {
    $includers = [];
    $lines = explode("\n", $content);
    $inImportBlock = false;
    
    foreach ($lines as $lineNum => $line) {
      $trimmed = trim($line);
      
      // Start of import block
      if (preg_match('/^import\s*\(/', $trimmed)) {
        $inImportBlock = true;
        continue;
      }
      
      // End of import block
      if ($inImportBlock && $trimmed === ')') {
        $inImportBlock = false;
        continue;
      }
      
      // Inside import block
      if ($inImportBlock) {
        if (preg_match('/"([^"]+)"/', $trimmed, $matches)) {
          $includers[] = [
            'type' => 'import',
            'value' => $matches[1],
            'line' => $lineNum + 1
          ];
        }
      }
      
      // Single line import
      if (preg_match('/^import\s+"([^"]+)"/', $trimmed, $matches)) {
        $includers[] = [
          'type' => 'import',
          'value' => $matches[1],
          'line' => $lineNum + 1
        ];
      }
    }
    
    return $includers;
  }

  /**
   * Parse Dockerfile FROM statements
   */
  private function parseDockerfileFrom($content)
  {
    $includers = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $lineNum => $line) {
      $trimmed = trim($line);
      
      // Match FROM statements
      if (preg_match('/^FROM\s+([^\s]+)/i', $trimmed, $matches)) {
        $includers[] = [
          'type' => 'FROM',
          'value' => $matches[1],
          'line' => $lineNum + 1
        ];
      }
    }
    
    return $includers;
  }
}
