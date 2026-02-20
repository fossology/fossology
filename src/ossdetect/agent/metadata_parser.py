#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Metadata parser for various package manager files.

This module handles parsing of different metadata file formats commonly
used in software projects to declare dependencies. It extracts dependency
information and normalizes it into a consistent format.

Copyright (C) 2026 Fossology contributors
SPDX-License-Identifier: GPL-2.0-only
"""

import os
import json
import xml.etree.ElementTree as ET
import re
from typing import List, Dict, Optional

try:
    import toml
except ImportError:
    toml = None

try:
    import yaml
except ImportError:
    yaml = None


class Dependency:
    """Represents a single dependency with its metadata."""
    
    def __init__(self, name: str, version: Optional[str] = None, 
                 scope: Optional[str] = None, source_line: int = 0):
        self.name = name
        self.version = version or "unspecified"
        self.scope = scope or "runtime"
        self.source_line = source_line
    
    def to_dict(self) -> Dict:
        return {
            'name': self.name,
            'version': self.version,
            'scope': self.scope,
            'line': self.source_line
        }


class MetadataParser:
    """Base class for metadata parsers."""
    
    @staticmethod
    def detect_file_type(filepath: str) -> Optional[str]:
        """
        Detect the type of metadata file based on filename.
        Returns the parser type identifier or None if unsupported.
        """
        filename = os.path.basename(filepath)
        
        # Map filenames to parser types
        type_map = {
            'pom.xml': 'maven',
            'package.json': 'npm',
            'requirements.txt': 'pip',
            'go.mod': 'gomod',
            'Gemfile': 'bundler',
            'Cargo.toml': 'cargo'
        }
        
        return type_map.get(filename)
    
    @staticmethod
    def parse_file(filepath: str) -> Dict:
        """
        Parse a metadata file and return extracted dependencies.
        
        Returns a dict with:
          - file: path to the parsed file
          - file_type: detected type (maven, npm, etc.)
          - dependencies: list of Dependency objects
          - error: error message if parsing failed
        """
        result = {
            'file': filepath,
            'file_type': None,
            'dependencies': [],
            'error': None
        }
        
        file_type = MetadataParser.detect_file_type(filepath)
        if not file_type:
            result['error'] = f"Unsupported file type: {os.path.basename(filepath)}"
            return result
        
        result['file_type'] = file_type
        
        try:
            # Route to appropriate parser
            if file_type == 'maven':
                deps = MavenParser.parse(filepath)
            elif file_type == 'npm':
                deps = NpmParser.parse(filepath)
            elif file_type == 'pip':
                deps = PipParser.parse(filepath)
            elif file_type == 'gomod':
                deps = GoModParser.parse(filepath)
            elif file_type == 'bundler':
                deps = BundlerParser.parse(filepath)
            elif file_type == 'cargo':
                deps = CargoParser.parse(filepath)
            else:
                deps = []
            
            result['dependencies'] = [d.to_dict() for d in deps]
            
        except Exception as e:
            result['error'] = str(e)
        
        return result


class MavenParser:
    """Parser for Maven pom.xml files."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from pom.xml."""
        deps = []
        
        try:
            tree = ET.parse(filepath)
            root = tree.getroot()
            
            # Maven uses XML namespaces, need to handle that
            namespace = {'mvn': 'http://maven.apache.org/POM/4.0.0'}
            
            # Try without namespace first (some poms don't use it)
            dep_elements = root.findall('.//dependency')
            if not dep_elements:
                # Try with namespace
                dep_elements = root.findall('.//mvn:dependency', namespace)
            
            for dep in dep_elements:
                # Extract groupId, artifactId, version
                group_id = MavenParser._get_text(dep, 'groupId', namespace)
                artifact_id = MavenParser._get_text(dep, 'artifactId', namespace)
                version = MavenParser._get_text(dep, 'version', namespace)
                scope = MavenParser._get_text(dep, 'scope', namespace) or 'compile'
                
                if group_id and artifact_id:
                    # Maven convention: groupId:artifactId
                    name = f"{group_id}:{artifact_id}"
                    deps.append(Dependency(name, version, scope))
        
        except ET.ParseError as e:
            # Don't fail completely on malformed XML, just skip it
            print(f"Warning: Could not parse {filepath}: {e}")
        
        return deps
    
    @staticmethod
    def _get_text(element, tag, namespace):
        """Helper to get text from XML element, trying with and without namespace."""
        child = element.find(tag)
        if child is None:
            child = element.find(f"mvn:{tag}", namespace)
        return child.text if child is not None else None


class NpmParser:
    """Parser for npm package.json files."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from package.json."""
        deps = []
        
        with open(filepath, 'r', encoding='utf-8') as f:
            try:
                data = json.load(f)
            except json.JSONDecodeError:
                return deps  # Return empty list on parse error
        
        # npm has multiple dependency sections
        dep_sections = {
            'dependencies': 'runtime',
            'devDependencies': 'development',
            'peerDependencies': 'peer',
            'optionalDependencies': 'optional'
        }
        
        for section, scope in dep_sections.items():
            if section in data and isinstance(data[section], dict):
                for name, version in data[section].items():
                    deps.append(Dependency(name, version, scope))
        
        return deps


class PipParser:
    """Parser for pip requirements.txt files."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from requirements.txt."""
        deps = []
        
        with open(filepath, 'r', encoding='utf-8') as f:
            for line_num, line in enumerate(f, 1):
                line = line.strip()
                
                # Skip comments and empty lines
                if not line or line.startswith('#'):
                    continue
                
                # Parse requirement line
                # Format can be: package==1.0, package>=1.0, package, etc.
                dep = PipParser._parse_requirement(line, line_num)
                if dep:
                    deps.append(dep)
        
        return deps
    
    @staticmethod
    def _parse_requirement(line: str, line_num: int) -> Optional[Dependency]:
        """Parse a single requirement line."""
        # Remove inline comments
        line = re.sub(r'\s+#.*$', '', line)
        
        # Match package name and version specifier
        # Handles: package, package==1.0, package>=1.0, etc.
        match = re.match(r'^([a-zA-Z0-9\-_.]+)\s*([><=!~]+)?\s*(.*)$', line)
        
        if match:
            name = match.group(1)
            version_op = match.group(2) or ''
            version_num = match.group(3) or ''
            
            version = f"{version_op}{version_num}".strip() if version_op else "any"
            
            return Dependency(name, version, source_line=line_num)
        
        return None


class GoModParser:
    """Parser for Go go.mod files."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from go.mod."""
        deps = []
        
        with open(filepath, 'r', encoding='utf-8') as f:
            in_require_block = False
            
            for line_num, line in enumerate(f, 1):
                line = line.strip()
                
                # Skip comments
                if line.startswith('//'):
                    continue
                
                # Check for require block
                if line.startswith('require ('):
                    in_require_block = True
                    continue
                
                if in_require_block:
                    if line == ')':
                        in_require_block = False
                        continue
                    
                    # Parse dependency line: module version
                    parts = line.split()
                    if len(parts) >= 2:
                        name = parts[0]
                        version = parts[1]
                        deps.append(Dependency(name, version, source_line=line_num))
                
                # Handle single-line require
                elif line.startswith('require '):
                    parts = line[8:].split()
                    if len(parts) >= 2:
                        name = parts[0]
                        version = parts[1]
                        deps.append(Dependency(name, version, source_line=line_num))
        
        return deps


class BundlerParser:
    """Parser for Ruby Gemfile."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from Gemfile."""
        deps = []
        
        with open(filepath, 'r', encoding='utf-8') as f:
            for line_num, line in enumerate(f, 1):
                line = line.strip()
                
                # Skip comments and empty lines
                if not line or line.startswith('#'):
                    continue
                
                # Match gem declarations: gem 'name', 'version'
                match = re.match(r"gem\s+['\"]([^'\"]+)['\"](?:\s*,\s*['\"]([^'\"]+)['\"])?", line)
                
                if match:
                    name = match.group(1)
                    version = match.group(2) or 'latest'
                    deps.append(Dependency(name, version, source_line=line_num))
        
        return deps


class CargoParser:
    """Parser for Rust Cargo.toml files."""
    
    @staticmethod
    def parse(filepath: str) -> List[Dependency]:
        """Extract dependencies from Cargo.toml."""
        deps = []
        
        if toml is None:
            print("Warning: toml library not available, skipping Cargo.toml parsing")
            return deps
        
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                data = toml.load(f)
            
            # Cargo has multiple dependency sections
            dep_sections = {
                'dependencies': 'runtime',
                'dev-dependencies': 'development',
                'build-dependencies': 'build'
            }
            
            for section, scope in dep_sections.items():
                if section in data and isinstance(data[section], dict):
                    for name, spec in data[section].items():
                        # Version can be a string or a dict with version key
                        if isinstance(spec, str):
                            version = spec
                        elif isinstance(spec, dict) and 'version' in spec:
                            version = spec['version']
                        else:
                            version = 'latest'
                        
                        deps.append(Dependency(name, version, scope))
        
        except Exception as e:
            print(f"Warning: Could not parse {filepath}: {e}")
        
        return deps


if __name__ == "__main__":
    import sys
    import argparse
    
    parser = argparse.ArgumentParser(description="Parse metadata files and extract dependencies")
    parser.add_argument('filepath', help='Path to the metadata file to parse')
    parser.add_argument('-o', '--output', help='Output JSON file (default: stdout)')
    
    args = parser.parse_args()
    
    if not os.path.exists(args.filepath):
        print(f"Error: File not found: {args.filepath}", file=sys.stderr)
        sys.exit(1)
    
    result = MetadataParser.parse_file(args.filepath)
    
    if args.output:
        with open(args.output, 'w') as f:
            json.dump(result, f, indent=2)
    else:
        print(json.dumps(result, indent=2))
