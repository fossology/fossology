"""
Enhanced Hybrid Engine with sophisticated decision logic
Combines rule-based and ML predictions with weighted voting
"""

from typing import List
from schemas import LicenseResult, ScanResponse
from rule_engine import RuleEngine
from ml_engine import MLEngine

class HybridEngine:
    def __init__(self):
        self.rule_engine = RuleEngine()
        self.ml_engine = MLEngine()
        
        # Weights for hybrid decision
        self.rule_weight = 0.6  # Rule-based gets higher weight (more precise)
        self.ml_weight = 0.4    # ML gets lower weight (broader coverage)
    
    def analyze(self, text: str) -> ScanResponse:
        """
        Perform hybrid analysis combining rule-based and ML approaches
        
        Args:
            text: Cleaned text to analyze
            
        Returns:
            ScanResponse with detected licenses and metadata
        """
        # 1. Rule-based scan
        rule_results = self.rule_engine.scan(text)
        
        # 2. ML-based scan
        ml_results = self.ml_engine.predict(text)
        
        # 3. Hybrid decision: weighted voting
        combined_results = self._weighted_voting(rule_results, ml_results)
        
        # 4. Conflict detection
        conflict = self._detect_conflicts(combined_results)
        
        # 5. Generate message
        message = self._generate_message(combined_results, rule_results, ml_results, conflict)
        
        return ScanResponse(
            detected_licenses=combined_results,
            conflict_detected=conflict,
            message=message
        )
    
    def _weighted_voting(self, 
                        rule_results: List[LicenseResult],
                        ml_results: List[LicenseResult]) -> List[LicenseResult]:
        """
        Combine results using weighted voting
        
        Args:
            rule_results: Results from rule engine
            ml_results: Results from ML engine
            
        Returns:
            Combined and sorted list of LicenseResult
        """
        # Collect all unique licenses
        license_scores = {}
        
        # Add rule-based scores
        for result in rule_results:
            license_scores[result.license_name] = {
                'rule': result.confidence,
                'ml': 0.0,
                'rule_method': result.method
            }
        
        # Add ML scores
        for result in ml_results:
            if result.license_name not in license_scores:
                license_scores[result.license_name] = {
                    'rule': 0.0,
                    'ml': 0.0,
                    'rule_method': None
                }
            license_scores[result.license_name]['ml'] = result.confidence
        
        # Calculate weighted scores
        combined = []
        for license_name, scores in license_scores.items():
            # Weighted average
            final_confidence = (
                self.rule_weight * scores['rule'] +
                self.ml_weight * scores['ml']
            )
            
            # Boost confidence if both methods agree
            if scores['rule'] > 0.5 and scores['ml'] > 0.5:
                final_confidence = min(1.0, final_confidence * 1.2)
            
            # Determine method
            if scores['rule'] > scores['ml']:
                method = 'hybrid-rule'
            elif scores['ml'] > scores['rule']:
                method = 'hybrid-ml'
            else:
                method = 'hybrid'
            
            combined.append(LicenseResult(
                license_name=license_name,
                confidence=float(final_confidence),
                method=method
            ))
        
        # Sort by confidence
        combined.sort(key=lambda x: x.confidence, reverse=True)
        
        # Filter out very low confidence results
        combined = [r for r in combined if r.confidence > 0.15]
        
        return combined
    
    def _detect_conflicts(self, results: List[LicenseResult]) -> bool:
        """
        Detect conflicts between top license candidates
        
        Args:
            results: Sorted list of license results
            
        Returns:
            True if conflict detected, False otherwise
        """
        if len(results) < 2:
            return False
        
        # Conflict if top 2 results have similar high confidence
        top1 = results[0]
        top2 = results[1]
        
        # Conflict criteria:
        # - Both have confidence > 0.6
        # - Confidence difference < 0.15
        if (top1.confidence > 0.6 and 
            top2.confidence > 0.6 and 
            abs(top1.confidence - top2.confidence) < 0.15):
            return True
        
        return False
    
    def _generate_message(self,
                         combined: List[LicenseResult],
                         rule_results: List[LicenseResult],
                         ml_results: List[LicenseResult],
                         conflict: bool) -> str:
        """Generate informative message about the scan results"""
        if not combined:
            return "No license detected. The text may not contain recognizable license information."
        
        top_license = combined[0]
        
        if conflict:
            return f"Potential conflict detected. Top candidates: {combined[0].license_name} ({combined[0].confidence:.2f}) and {combined[1].license_name} ({combined[1].confidence:.2f}). Manual review recommended."
        
        if top_license.confidence > 0.85:
            return f"High confidence detection: {top_license.license_name} (confidence: {top_license.confidence:.2f})"
        elif top_license.confidence > 0.6:
            return f"Detected: {top_license.license_name} (confidence: {top_license.confidence:.2f})"
        else:
            return f"Low confidence detection: {top_license.license_name} (confidence: {top_license.confidence:.2f}). Manual review recommended."
