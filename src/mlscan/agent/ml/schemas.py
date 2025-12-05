"""
Pydantic schemas for API request/response models
"""

from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class ScanRequest(BaseModel):
    """Request model for text-based license scanning"""
    text: str

class FileUploadRequest(BaseModel):
    """Request model for file-based license scanning"""
    filename: str
    content: str

class LicenseResult(BaseModel):
    """Individual license detection result"""
    license_name: str
    confidence: float
    method: str  # "rule", "ml", "hybrid", etc.

class ScanResponse(BaseModel):
    """Response model for license scanning"""
    detected_licenses: List[LicenseResult]
    conflict_detected: bool
    message: Optional[str] = None
    processing_time_ms: Optional[float] = None

class ComplianceReport(BaseModel):
    """Detailed compliance report"""
    timestamp: str
    source_file: Optional[str] = None
    summary: Dict[str, Any]
    licenses: List[Dict[str, Any]]
    compatibility_analysis: Dict[str, Any]
    risk_assessment: Dict[str, Any]
    recommendations: List[str]

class LicenseInfo(BaseModel):
    """Information about a specific license"""
    id: str
    name: str
    keywords: List[str]
    regex: Optional[str] = None

class HealthResponse(BaseModel):
    """Health check response"""
    status: str
    version: str
    models_loaded: Dict[str, bool]
    supported_licenses: int
