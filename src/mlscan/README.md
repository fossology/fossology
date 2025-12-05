# ML License Scanner (mlscan) - Quick Start Guide

## 🚀 Quick Start

### Correct Path
The mlscan agent is located at:
```
/Users/puneethadityamyakam/Documents/fossology_ojt/fossology/src/mlscan/
```

### Installation

1. **Navigate to the mlscan directory:**
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
```

2. **Install Python dependencies:**
```bash
./install_deps.sh
```

Or manually:
```bash
cd agent/ml
pip3 install -r requirements.txt
```

### Testing

**Quick test:**
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
./test.sh
```

**Manual test:**
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan/agent/ml
python3 test_ml.py
```

---

## 📁 What Was Created

### Core Components

1. **Python ML Code** (`agent/ml/`)
   - `mlscan_runner.py` - Main entry point
   - `preprocessing.py` - Text preprocessing + BERT
   - `ml_engine.py` - TF-IDF + BERT ensemble
   - `rule_engine.py` - SPDX rule-based detection
   - `hybrid_engine.py` - Hybrid decision engine
   - `bert_classifier.py` - BERT classifier
   - `schemas.py` - Data models
   - `utils/trie.py` - Trie data structure

2. **C++ Wrapper** (`agent/`)
   - `mlscan.cc` - Main agent entry point
   - `mlscan_wrapper.cc` - Python wrapper
   - `mlscan_dbhandler.cc` - Database operations

3. **Configuration**
   - `mlscan.conf` - Scheduler configuration
   - `CMakeLists.txt` - Build system
   - `mod_deps` - Dependencies

4. **Data** (`data/`)
   - `spdx_rules.json` - SPDX license patterns
   - `training_data.csv` - ML training data
   - `ml_model.pkl` - Trained TF-IDF model
   - `bert_embeddings.json` - BERT embeddings

5. **Scripts**
   - `install_deps.sh` - Install Python dependencies
   - `test.sh` - Quick test script

---

## 🔧 How It Works

### Detection Pipeline

```
Input File → Preprocessing → Rule Engine + ML Engine → Hybrid Decision → Results
```

### ML Ensemble

- **TF-IDF Classifier** (40% weight): Fast text-based classification
- **BERT Classifier** (60% weight): Semantic understanding
- **Rule Engine** (60% weight): SPDX pattern matching
- **Hybrid Decision**: Weighted voting for final prediction

### Output

JSON format with:
- Detected licenses
- Confidence scores (0.0-1.0)
- Detection method (rule/ml-tfidf/ml-bert/hybrid)
- Conflict detection

---

## 📊 Database Schema

Tables created:
- `mlscan_license` - ML license findings
- `mlscan_ars` - Audit trail

---

## 🎯 Next Steps

### To Complete Integration:

1. **Build the C++ agent:**
```bash
cd ~/Documents/fossology_ojt/fossology
mkdir -p build && cd build
cmake ..
make mlscan
```

2. **Apply database schema:**
```bash
psql -U fossology -d fossology < ~/Documents/fossology_ojt/fossology/src/mlscan/agent/mlscan_schema.sql
```

3. **Test standalone:**
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
./test.sh
```

### Remaining Work:

- [ ] UI components (PHP)
- [ ] Full scheduler integration
- [ ] Integration tests
- [ ] Documentation updates

---

## 📝 Usage Examples

### Python ML Scanner (Standalone)

```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan/agent/ml

# Scan a single file
python3 mlscan_runner.py /path/to/file.txt /tmp/output.json

# View results
cat /tmp/output.json
```

### Expected Output

```json
{
  "file": "/path/to/file.txt",
  "licenses": [
    {
      "license_name": "MIT",
      "confidence": 0.95,
      "method": "hybrid"
    }
  ],
  "conflict_detected": false,
  "message": "High confidence detection: MIT (confidence: 0.95)"
}
```

---

## 🐛 Troubleshooting

### "No such file or directory"
Make sure you're using the full path:
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
```

### "No module named 'sentence_transformers'"
Install Python dependencies:
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
./install_deps.sh
```

### Build errors
Make sure you have:
- Python 3.8+
- libjsoncpp-dev
- cmake
- FOSSology libraries

---

## 📚 Documentation

See the full walkthrough for detailed information:
- Implementation plan
- Architecture details
- Performance metrics
- Integration guide
