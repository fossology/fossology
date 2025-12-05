# ML License Scanner - Developer Guide

## Overview

This guide provides detailed information for developers working on the ML License Scanner (mlscan) agent for FOSSology.

---

## Architecture

### Component Overview

```
┌─────────────────────────────────────────┐
│         FOSSology Scheduler             │
└──────────────┬──────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────┐
│      mlscan.cc (C++ Main Agent)         │
│  - Scheduler communication              │
│  - Upload processing                    │
│  - Database operations                  │
└──────────────┬──────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────┐
│   mlscan_runner.py (Python Wrapper)     │
│  - File scanning orchestration          │
│  - JSON result formatting               │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴───────┐
       ↓               ↓
┌─────────────┐ ┌─────────────┐
│ Rule Engine │ │  ML Engine  │
│  - Trie     │ │  - TF-IDF   │
│  - Regex    │ │  - BERT     │
└──────┬──────┘ └──────┬──────┘
       │               │
       └───────┬───────┘
               ↓
     ┌─────────────────┐
     │ Hybrid Decision │
     │  - Weighted     │
     │  - Voting       │
     └────────┬────────┘
              ↓
     ┌─────────────────┐
     │ Database Storage│
     └─────────────────┘
```

### Data Flow

1. **Input**: FOSSology scheduler sends upload ID
2. **Processing**: C++ agent queries database for files
3. **Scanning**: Python ML scanner processes each file
4. **Analysis**: Hybrid engine combines rule and ML results
5. **Storage**: Results stored in `mlscan_license` table
6. **Display**: PHP UI shows results with confidence scores

---

## Development Setup

### Prerequisites

```bash
# System packages
sudo apt-get install python3 python3-pip libjsoncpp-dev cmake postgresql

# Python dependencies
cd ~/Documents/fossology_ojt/fossology/src/mlscan
./install_deps.sh
```

### Building

```bash
cd ~/Documents/fossology_ojt/fossology
mkdir -p build && cd build
cmake ..
make mlscan
```

### Running Tests

```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan

# Python unit tests
./agent_tests/run_tests.sh

# Quick integration test
./test.sh
```

---

## Code Structure

### Python ML Components

**Location**: `agent/ml/`

#### mlscan_runner.py
Main entry point called by C++ wrapper.
- Accepts file path and output JSON path
- Orchestrates preprocessing and analysis
- Returns JSON with licenses and confidence scores

#### preprocessing.py
Text preprocessing and BERT embeddings.
- `clean_text()`: Normalize and clean input text
- `preprocess_for_detection()`: Full preprocessing pipeline

#### rule_engine.py
SPDX rule-based detection.
- Uses Trie tree for efficient keyword matching
- Regex patterns for license identifiers
- Confidence scoring based on match strength

#### ml_engine.py
Machine learning classification.
- TF-IDF with Logistic Regression (40% weight)
- BERT semantic classifier (60% weight)
- Ensemble voting for final prediction

#### hybrid_engine.py
Combines rule and ML predictions.
- Weighted voting (Rule 60%, ML 40%)
- Confidence boosting when methods agree
- Conflict detection

### C++ Agent Components

**Location**: `agent/`

#### mlscan.cc
Main agent with scheduler integration.
- `main()`: Scheduler loop and job processing
- `processUpload()`: Process all files in upload
- `writeARS()`: Audit trail management

#### mlscan_wrapper.cc
Python wrapper functions.
- `runPythonScanner()`: Execute Python script
- `exec()`: Capture command output
- `readJsonFile()`: Parse JSON results

#### mlscan_dbhandler.cc
Database operations.
- `createTables()`: Initialize schema
- `storeScanResult()`: Save ML predictions
- `isAlreadyScanned()`: Check for duplicates

#### mlscan_state.cc
Agent state management.
- Stores agent ID and configuration
- CLI options handling

### UI Components

**Location**: `ui/`

#### agent-mlscan.php
Agent scheduling plugin.
- Registers mlscan in FOSSology UI
- Handles job scheduling

#### ui-view-mlscan.php
Results viewer.
- Displays licenses with confidence scores
- Color-coded progress bars
- Detection method badges

---

## Database Schema

### mlscan_license Table

Stores ML license detection results.

```sql
CREATE TABLE mlscan_license (
  ml_pk SERIAL PRIMARY KEY,
  pfile_fk INTEGER NOT NULL,        -- File ID
  rf_fk INTEGER,                     -- License reference ID
  agent_fk INTEGER NOT NULL,         -- Agent ID
  confidence REAL NOT NULL,          -- 0.0 to 1.0
  detection_method VARCHAR(50),      -- 'rule', 'ml-tfidf', 'ml-bert', 'hybrid'
  UNIQUE(pfile_fk, rf_fk, agent_fk)
);
```

### mlscan_ars Table

Audit trail for ML scans.

```sql
CREATE TABLE mlscan_ars (
  ars_pk SERIAL PRIMARY KEY,
  upload_fk INTEGER NOT NULL,        -- Upload ID
  agent_fk INTEGER NOT NULL,         -- Agent ID
  ars_success BOOLEAN NOT NULL,      -- Success flag
  ars_starttime TIMESTAMP,           -- Start time
  ars_endtime TIMESTAMP              -- End time
);
```

---

## Adding New Features

### Adding a New ML Model

1. Create new classifier in `agent/ml/`:
```python
# new_classifier.py
class NewClassifier:
    def predict(self, text):
        # Your implementation
        return predictions
```

2. Update `ml_engine.py`:
```python
from new_classifier import NewClassifier

class MLEngine:
    def __init__(self):
        self.new_classifier = NewClassifier()
    
    def predict(self, text):
        # Add new predictions to ensemble
        new_results = self.new_classifier.predict(text)
        # Combine with existing results
```

3. Update weights in `hybrid_engine.py` if needed

### Adding New Detection Methods

1. Update `rule_engine.py` with new patterns
2. Add to `spdx_rules.json`:
```json
{
  "id": "NEW-LICENSE",
  "keywords": ["keyword1", "keyword2"],
  "regex": "pattern here"
}
```

---

## Testing

### Unit Tests

**Location**: `agent_tests/Unit/`

Run specific test:
```bash
cd agent_tests/Unit
python3 test_ml_components.py TestHybridEngine.test_analyze
```

### Integration Tests

Test full pipeline:
```bash
cd agent_tests/Unit
python3 test_integration.py
```

### Manual Testing

Test Python scanner directly:
```bash
cd agent/ml
python3 mlscan_runner.py /path/to/file.txt output.json
cat output.json
```

---

## Performance Optimization

### Current Performance
- **Average**: < 500ms per file
- **Accuracy**: ~94% (hybrid)

### Optimization Tips

1. **Batch Processing**: Process multiple files before database commits
2. **Model Caching**: Keep models in memory between scans
3. **GPU Acceleration**: Use CUDA for BERT if available
4. **Parallel Processing**: Process files in parallel

Example parallel processing:
```python
from multiprocessing import Pool

def scan_files_parallel(file_list, num_workers=4):
    with Pool(num_workers) as pool:
        results = pool.map(scan_file, file_list)
    return results
```

---

## Troubleshooting

### Common Issues

**Import errors**:
```bash
# Make sure you're in the right directory
cd ~/Documents/fossology_ojt/fossology/src/mlscan/agent/ml
python3 -c "import preprocessing"
```

**Model not found**:
```bash
# Check data files exist
ls -la ../../data/
```

**Database connection**:
```sql
-- Test database connection
psql -U fossology -d fossology -c "SELECT * FROM mlscan_license LIMIT 1;"
```

---

## Contributing

### Code Style

**Python**: Follow PEP 8
```bash
pip install black flake8
black agent/ml/*.py
flake8 agent/ml/*.py
```

**C++**: Follow FOSSology style
```bash
clang-format -i agent/*.cc agent/*.hpp
```

### Pull Request Process

1. Create feature branch
2. Add tests for new features
3. Update documentation
4. Run all tests
5. Submit PR with description

---

## References

- [FOSSology Documentation](https://www.fossology.org/get-started/basic-workflow/)
- [SPDX License List](https://spdx.org/licenses/)
- [BERT Documentation](https://huggingface.co/docs/transformers/model_doc/bert)
- [scikit-learn](https://scikit-learn.org/stable/)
