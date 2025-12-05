"""
Enhanced Report Generator with detailed compliance analysis
"""

from typing import List, Dict, Any
from datetime import datetime
from schemas import LicenseResult

class ReportGenerator:
    def __init__(self):
        # License compatibility matrix (simplified)
        self.compatibility = {
            'MIT': {'compatible_with': ['Apache-2.0', 'BSD-3-Clause', 'GPL-3.0', 'LGPL-3.0'], 'permissive': True},
            'Apache-2.0': {'compatible_with': ['MIT', 'BSD-3-Clause', 'GPL-3.0'], 'permissive': True},
            'BSD-3-Clause': {'compatible_with': ['MIT', 'Apache-2.0', 'GPL-3.0'], 'permissive': True},
            'GPL-3.0': {'compatible_with': ['LGPL-3.0'], 'permissive': False, 'copyleft': True},
            'LGPL-3.0': {'compatible_with': ['GPL-3.0', 'MIT', 'Apache-2.0'], 'permissive': False},
            'MPL-2.0': {'compatible_with': ['Apache-2.0', 'GPL-3.0'], 'permissive': False},
        }
        
        self.risk_levels = {
            'permissive': 'LOW',
            'weak_copyleft': 'MEDIUM',
            'strong_copyleft': 'HIGH',
            'proprietary': 'CRITICAL'
        }
    
    def generate_report(self, 
                       detected_licenses: List[LicenseResult],
                       conflict_detected: bool,
                       source_file: str = None) -> Dict[str, Any]:
        """
        Generate a comprehensive compliance report
        
        Args:
            detected_licenses: List of detected license results
            conflict_detected: Whether conflicts were detected
            source_file: Optional source file name
            
        Returns:
            Dictionary containing the compliance report
        """
        report = {
            'timestamp': datetime.now().isoformat(),
            'source_file': source_file,
            'summary': {
                'total_licenses_detected': len(detected_licenses),
                'conflict_detected': conflict_detected,
                'primary_license': detected_licenses[0].license_name if detected_licenses else None,
                'confidence': detected_licenses[0].confidence if detected_licenses else 0.0
            },
            'licenses': [],
            'compatibility_analysis': {},
            'risk_assessment': {},
            'recommendations': []
        }
        
        # Add detailed license information
        for license_result in detected_licenses:
            license_info = {
                'name': license_result.license_name,
                'confidence': license_result.confidence,
                'detection_method': license_result.method,
                'properties': self._get_license_properties(license_result.license_name)
            }
            report['licenses'].append(license_info)
        
        # Compatibility analysis
        if len(detected_licenses) > 1:
            report['compatibility_analysis'] = self._analyze_compatibility(detected_licenses)
        
        # Risk assessment
        report['risk_assessment'] = self._assess_risks(detected_licenses)
        
        # Generate recommendations
        report['recommendations'] = self._generate_recommendations(
            detected_licenses, 
            conflict_detected,
            report['risk_assessment']
        )
        
        return report
    
    def _get_license_properties(self, license_name: str) -> Dict[str, Any]:
        """Get properties of a license"""
        if license_name in self.compatibility:
            return self.compatibility[license_name]
        return {'permissive': False, 'compatible_with': []}
    
    def _analyze_compatibility(self, licenses: List[LicenseResult]) -> Dict[str, Any]:
        """Analyze compatibility between detected licenses"""
        analysis = {
            'compatible': True,
            'conflicts': [],
            'details': []
        }
        
        for i, lic1 in enumerate(licenses):
            for lic2 in licenses[i+1:]:
                lic1_props = self._get_license_properties(lic1.license_name)
                compatible = lic2.license_name in lic1_props.get('compatible_with', [])
                
                detail = {
                    'license_1': lic1.license_name,
                    'license_2': lic2.license_name,
                    'compatible': compatible
                }
                analysis['details'].append(detail)
                
                if not compatible:
                    analysis['compatible'] = False
                    analysis['conflicts'].append(f"{lic1.license_name} ↔ {lic2.license_name}")
        
        return analysis
    
    def _assess_risks(self, licenses: List[LicenseResult]) -> Dict[str, Any]:
        """Assess risks associated with detected licenses"""
        risks = {
            'overall_risk': 'LOW',
            'risk_factors': []
        }
        
        max_risk = 'LOW'
        
        for license_result in licenses:
            props = self._get_license_properties(license_result.license_name)
            
            if props.get('copyleft'):
                risk_factor = {
                    'license': license_result.license_name,
                    'risk': 'HIGH',
                    'reason': 'Strong copyleft license - requires derivative works to use same license'
                }
                risks['risk_factors'].append(risk_factor)
                max_risk = 'HIGH'
            elif not props.get('permissive'):
                risk_factor = {
                    'license': license_result.license_name,
                    'risk': 'MEDIUM',
                    'reason': 'Non-permissive license - may have restrictions on use'
                }
                risks['risk_factors'].append(risk_factor)
                if max_risk == 'LOW':
                    max_risk = 'MEDIUM'
        
        risks['overall_risk'] = max_risk
        return risks
    
    def _generate_recommendations(self, 
                                 licenses: List[LicenseResult],
                                 conflict_detected: bool,
                                 risk_assessment: Dict[str, Any]) -> List[str]:
        """Generate actionable recommendations"""
        recommendations = []
        
        if not licenses:
            recommendations.append("No license detected. Consider adding a license to your project.")
            return recommendations
        
        if conflict_detected:
            recommendations.append("⚠️ License conflict detected. Review the compatibility analysis and consult legal counsel.")
        
        if risk_assessment['overall_risk'] == 'HIGH':
            recommendations.append("⚠️ High-risk license detected. Ensure compliance with copyleft requirements.")
            recommendations.append("Consider isolating GPL code in separate modules if combining with proprietary code.")
        
        if risk_assessment['overall_risk'] == 'MEDIUM':
            recommendations.append("Review license terms carefully before distribution.")
        
        # Check for low confidence
        if licenses and licenses[0].confidence < 0.7:
            recommendations.append("Low confidence detection. Manual review recommended.")
            recommendations.append("Consider adding a clear LICENSE file to your project.")
        
        # Positive recommendations
        if risk_assessment['overall_risk'] == 'LOW' and not conflict_detected:
            recommendations.append("✓ Permissive license detected with low compliance risk.")
            recommendations.append("✓ No compatibility conflicts detected.")
        
        return recommendations
