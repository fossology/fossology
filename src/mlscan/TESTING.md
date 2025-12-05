# Testing Guide for ML License Scanner

## Quick Test (Start Here!)

The easiest way to test if everything works:

```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan

# Run the simple test
python3 agent_tests/test_simple.py
```

**Expected output:**
```
Testing ML License Scanner...

1. Testing imports...
   ✓ Preprocessor imported
   ✓ RuleEngine imported
   ✓ MLEngine imported
   ✓ HybridEngine imported

2. Testing basic functionality...
   ✓ Preprocessing works
   ✓ Hybrid engine works
   ✓ Detected 1 licenses
   ✓ Top result: MIT (confidence: 0.95, method: hybrid)

==================================================
✓ All tests passed!
==================================================
```

---

## If You Get Errors

### Error: "No module named 'sentence_transformers'"

**Solution:** Install Python dependencies
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
./install_deps.sh
```

### Error: "ModuleNotFoundError: No module named 'preprocessing'"

**Solution:** Run from the correct directory
```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan
python3 agent_tests/test_simple.py
```

### Error: "FileNotFoundError: data/spdx_rules.json"

**Solution:** Make sure data files exist
```bash
ls -la data/
# Should show: spdx_rules.json, training_data.csv, ml_model.pkl, bert_embeddings.json
```

---

## Testing Individual Components

### Test Python Scanner Directly

```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan/agent/ml

# Create test file
cat > test_mit.txt << 'EOF'
MIT License

Copyright (c) 2024 Test

Permission is hereby granted, free of charge...
EOF

# Scan it
python3 mlscan_runner.py test_mit.txt output.json

# View results
cat output.json
```

### Test Different Licenses

```bash
cd ~/Documents/fossology_ojt/fossology/src/mlscan/agent/ml

# Apache License
echo "Apache License Version 2.0" > test_apache.txt
python3 mlscan_runner.py test_apache.txt apache_result.json
cat apache_result.json

# GPL License  
echo "GNU GENERAL PUBLIC LICENSE Version 2" > test_gpl.txt
python3 mlscan_runner.py test_gpl.txt gpl_result.json
cat gpl_result.json
```

---

## Understanding Test Results

### Good Result (High Confidence)
```json
{
  "licenses": [
    {
      "license_name": "MIT",
      "confidence": 0.95,
      "method": "hybrid"
    }
  ],
  "conflict_detected": false
}
```
✅ **Interpretation:** Very confident it's MIT license

### Low Confidence Result
```json
{
  "licenses": [
    {
      "license_name": "Apache-2.0",
      "confidence": 0.35,
      "method": "ml-bert"
    }
  ]
}
```
⚠️ **Interpretation:** Low confidence, manual review needed

### Multiple Licenses
```json
{
  "licenses": [
    {"license_name": "MIT", "confidence": 0.85},
    {"license_name": "BSD-3-Clause", "confidence": 0.78}
  ],
  "conflict_detected": true
}
```
⚠️ **Interpretation:** Possible dual licensing or conflict

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Import errors | Check you're in `/mlscan` directory |
| Missing modules | Run `./install_deps.sh` |
| Slow first run | BERT models downloading (~400MB) |
| Low accuracy | Check if data files are present |

---

## Next Steps

Once the simple test passes:
1. Try scanning real license files
2. Test with your own code files
3. Compare results with other scanners (nomos, monk)
4. Build the C++ agent (optional)

For more details, see:
- [README.md](README.md) - Quick start
- [DEVELOPER.md](DEVELOPER.md) - Developer guide
- [USER_GUIDE.md](USER_GUIDE.md) - User guide
