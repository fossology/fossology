"""
Enhanced Rule Engine with Trie-based keyword matching
Supports 40+ SPDX licenses with efficient pattern matching
"""

import json
import re
from typing import List, Dict, Any
from schemas import LicenseResult
from utils.trie import Trie

class RuleEngine:
    def __init__(self, rules_path: str = "../../data/spdx_rules.json"):
        self.rules = self._load_rules(rules_path)
        self.trie = Trie()
        self._build_trie()
    
    def _load_rules(self, path: str) -> List[Dict[str, Any]]:
        """Load SPDX rules from JSON file"""
        try:
            with open(path, 'r') as f:
                rules = json.load(f)
                print(f"Loaded {len(rules)} SPDX license rules")
                return rules
        except FileNotFoundError:
            print(f"Warning: Rules file not found at {path}")
            return []
        except json.JSONDecodeError as e:
            print(f"Error parsing rules file: {e}")
            return []
    
    def _build_trie(self):
        """Build Trie tree from keywords in rules"""
        for rule in self.rules:
            license_id = rule['id']
            for keyword in rule.get('keywords', []):
                self.trie.insert(keyword, license_id)
        print(f"Trie tree built with keywords from {len(self.rules)} licenses")
    
    def scan(self, text: str) -> List[LicenseResult]:
        """
        Scan text for license matches using rules
        
        Args:
            text: Cleaned text to scan
            
        Returns:
            List of LicenseResult objects
        """
        detected = []
        text_lower = text.lower()
        
        # Use Trie for efficient keyword matching
        keyword_matches = self.trie.search_text(text_lower)
        
        # Score each license based on matches
        license_scores = {}
        
        for rule in self.rules:
            license_id = rule['id']
            score = 0
            
            # Add keyword match score from Trie
            if license_id in keyword_matches:
                score += keyword_matches[license_id]
            
            # Regex matching (stronger signal)
            if rule.get('regex'):
                try:
                    if re.search(rule['regex'], text, re.IGNORECASE):
                        score += 10  # Regex match is strong evidence
                except re.error as e:
                    print(f"Regex error for {license_id}: {e}")
            
            # Calculate confidence based on score
            if score > 0:
                # Normalize confidence to 0-1 range
                # Higher scores get higher confidence
                confidence = min(1.0, score / 15.0)
                
                # Boost confidence if regex matched
                if score >= 10:
                    confidence = max(confidence, 0.85)
                
                license_scores[license_id] = {
                    'score': score,
                    'confidence': confidence
                }
        
        # Create LicenseResult objects for matches
        for license_id, data in license_scores.items():
            detected.append(LicenseResult(
                license_name=license_id,
                confidence=data['confidence'],
                method='rule'
            ))
        
        # Sort by confidence
        detected.sort(key=lambda x: x.confidence, reverse=True)
        
        return detected
    
    def get_license_info(self, license_id: str) -> Dict[str, Any]:
        """
        Get detailed information about a specific license
        
        Args:
            license_id: SPDX license identifier
            
        Returns:
            Dictionary with license information
        """
        for rule in self.rules:
            if rule['id'] == license_id:
                return rule
        return None
    
    def list_supported_licenses(self) -> List[str]:
        """Return list of all supported license IDs"""
        return [rule['id'] for rule in self.rules]
