#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Similarity matcher for OSS components.

Compares extracted dependencies against a database of known OSS components
and calculates similarity scores based on various factors.

Copyright (C) 2026 Fossology contributors
SPDX-License-Identifier: GPL-2.0-only
"""

import re
from typing import List, Dict, Tuple
from difflib import SequenceMatcher


class SimilarityMatch:
    """Represents a potential match between a dependency and known OSS component."""
    
    def __init__(self, component_name: str, component_version: str, 
                 score: float, match_type: str):
        self.component_name = component_name
        self.component_version = component_version
        self.score = score  # 0-100
        self.match_type = match_type  # 'exact', 'fuzzy', 'version'
    
    def to_dict(self) -> Dict:
        return {
            'component_name': self.component_name,
            'component_version': self.component_version,
            'score': round(self.score, 2),
            'match_type': self.match_type
        }


class SimilarityMatcher:
    """
    Matches dependencies against known OSS components.
    
    This is a simplified implementation that demonstrates the concept.
    In a production system, this would query a real database of OSS components.
    """
    
    def __init__(self, threshold: float = 80.0):
        """
        Initialize the matcher.
        
        Args:
            threshold: Minimum similarity score (0-100) to consider a match
        """
        self.threshold = threshold
        # Mock database of known components
        # In production, this would come from Fossology's database
        self.known_components = self._load_mock_components()
    
    def _load_mock_components(self) -> List[Dict]:
        """
        Load known OSS components.
        
        In production, this would query the database. For now, we return
        a small mock dataset for demonstration.
        """
        return [
            {'name': 'junit:junit', 'version': '4.13.2', 'type': 'maven'},
            {'name': 'com.google.guava:guava', 'version': '31.1-jre', 'type': 'maven'},
            {'name': 'express', 'version': '4.18.2', 'type': 'npm'},
            {'name': 'react', 'version': '18.2.0', 'type': 'npm'},
            {'name': 'lodash', 'version': '4.17.21', 'type': 'npm'},
            {'name': 'django', 'version': '4.2.0', 'type': 'pip'},
            {'name': 'requests', 'version': '2.31.0', 'type': 'pip'},
            {'name': 'numpy', 'version': '1.24.3', 'type': 'pip'},
        ]
    
    def find_matches(self, dependency_name: str, dependency_version: str) -> List[SimilarityMatch]:
        """
        Find potential matches for a given dependency.
        
        Args:
            dependency_name: Name of the dependency to match
            dependency_version: Version of the dependency
        
        Returns:
            List of SimilarityMatch objects, sorted by score (highest first)
        """
        matches = []
        
        for component in self.known_components:
            score = self._calculate_similarity(
                dependency_name, 
                dependency_version,
                component['name'],
                component['version']
            )
            
            if score >= self.threshold:
                # Determine match type
                match_type = self._determine_match_type(
                    dependency_name,
                    dependency_version,
                    component['name'],
                    component['version']
                )
                
                match = SimilarityMatch(
                    component['name'],
                    component['version'],
                    score,
                    match_type
                )
                matches.append(match)
        
        # Sort by score, highest first
        matches.sort(key=lambda m: m.score, reverse=True)
        
        return matches
    
    def _calculate_similarity(self, dep_name: str, dep_version: str,
                            comp_name: str, comp_version: str) -> float:
        """
        Calculate overall similarity score between dependency and component.
        
        The score is a weighted average of:
        - Name similarity (70% weight)
        - Version similarity (30% weight)
        """
        name_score = self._name_similarity(dep_name, comp_name)
        version_score = self._version_similarity(dep_version, comp_version)
        
        # Weighted average
        overall_score = (name_score * 0.7) + (version_score * 0.3)
        
        return overall_score
    
    def _name_similarity(self, name1: str, name2: str) -> float:
        """
        Calculate similarity between two package names.
        
        Uses a combination of exact matching and fuzzy string matching.
        """
        # Normalize names (lowercase, remove special chars)
        norm1 = self._normalize_name(name1)
        norm2 = self._normalize_name(name2)
        
        # Exact match
        if norm1 == norm2:
            return 100.0
        
        # Check if one contains the other
        if norm1 in norm2 or norm2 in norm1:
            return 90.0
        
        # Fuzzy string matching using SequenceMatcher
        ratio = SequenceMatcher(None, norm1, norm2).ratio()
        return ratio * 100.0
    
    def _version_similarity(self, ver1: str, ver2: str) -> float:
        """
        Calculate similarity between two version strings.
        
        Handles semantic versioning (major.minor.patch) and various formats.
        """
        # Handle unspecified versions
        if ver1 in ['unspecified', 'any', 'latest'] or \
           ver2 in ['unspecified', 'any', 'latest']:
            return 50.0  # Neutral score
        
        # Parse version numbers
        parts1 = self._parse_version(ver1)
        parts2 = self._parse_version(ver2)
        
        if not parts1 or not parts2:
            # Couldn't parse, fall back to string comparison
            return 100.0 if ver1 == ver2 else 30.0
        
        # Compare major.minor.patch
        score = 0.0
        weights = [50.0, 30.0, 20.0]  # major, minor, patch
        
        for i, (v1, v2, weight) in enumerate(zip(parts1, parts2, weights)):
            if v1 == v2:
                score += weight
            elif i == 0:
                # Different major version is a big deal
                score += weight * 0.3
            else:
                # Different minor/patch is less critical
                score += weight * 0.5
        
        return min(score, 100.0)
    
    def _normalize_name(self, name: str) -> str:
        """Normalize a package name for comparison."""
        # Convert to lowercase
        name = name.lower()
        # Remove common prefixes/suffixes
        name = re.sub(r'^(lib|py|node-|ruby-)', '', name)
        # Remove special characters, keep alphanumeric
        name = re.sub(r'[^a-z0-9]', '', name)
        return name
    
    def _parse_version(self, version: str) -> Tuple[int, ...]:
        """
        Parse a version string into numeric components.
        
        Examples:
          "1.2.3" -> (1, 2, 3)
          "4.18.2" -> (4, 18, 2)
          ">=2.0.0" -> (2, 0, 0)
        """
        # Remove version operators (>=, ==, ~, etc.)
        version = re.sub(r'^[><=~^]+', '', version)
        
        # Extract numeric parts
        match = re.match(r'(\d+)\.?(\d*)\.?(\d*)', version)
        
        if not match:
            return ()
        
        parts = []
        for group in match.groups():
            if group:
                parts.append(int(group))
            else:
                parts.append(0)
        
        return tuple(parts)
    
    def _determine_match_type(self, dep_name: str, dep_version: str,
                             comp_name: str, comp_version: str) -> str:
        """Determine the type of match (exact, fuzzy, or version-based)."""
        norm_dep = self._normalize_name(dep_name)
        norm_comp = self._normalize_name(comp_name)
        
        if norm_dep == norm_comp:
            if dep_version == comp_version:
                return 'exact'
            else:
                return 'version'
        else:
            return 'fuzzy'


if __name__ == "__main__":
    import json
    
    # Simple test
    matcher = SimilarityMatcher(threshold=70.0)
    
    test_deps = [
        ('junit:junit', '4.13.2'),
        ('react', '18.0.0'),
        ('Django', '4.2.0'),
        ('some-unknown-package', '1.0.0')
    ]
    
    for name, version in test_deps:
        print(f"\nSearching for: {name} @ {version}")
        matches = matcher.find_matches(name, version)
        
        if matches:
            print(f"Found {len(matches)} match(es):")
            for match in matches:
                print(f"  - {match.component_name} @ {match.component_version}")
                print(f"    Score: {match.score:.1f}% ({match.match_type})")
        else:
            print("  No matches found")
