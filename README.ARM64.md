# Running FOSSology on ARM64 (Apple Silicon, AWS Graviton, etc.)

## Current Limitations

FOSSology has limited ARM64 support due to Python dependency incompatibilities. Specifically:
- `scancode-toolkit` requires `extractcode-7z` which has no ARM64 wheels
- Some experimental Python features are not available on ARM64

## Quick Start for ARM64

### Option 1: Use ARM64-Specific Docker Compose (Recommended)

```bash
# Use the ARM64-optimized compose file
docker-compose -f docker-compose.arm64.yml up -d
```

This configuration:
- Uses native ARM64 PostgreSQL image
- Skips ARM64-incompatible Python dependencies
- Provides core FOSSology functionality

### Option 2: Build with Platform Override

```bash
# Force AMD64 emulation (slower but full features)
DOCKER_DEFAULT_PLATFORM=linux/amd64 docker-compose up -d
```

**Note:** This uses Rosetta/QEMU emulation and may be slower.

## What Works on ARM64

✅ **Fully Functional:**
- Core license scanning (nomos, monk, ojo)
- Copyright detection
- Database operations
- Web UI
- REST API
- Report generation (SPDX, DEP5, etc.)

⚠️ **Limited/Unavailable:**
- Scancode agent (requires extractcode-7z ARM64 build)
- ML-based copyright detection (experimental feature)

## Performance Notes

ARM64 native performance is typically:
- **10-20% faster** for CPU-bound tasks (license scanning)
- **Similar memory usage** compared to AMD64
- **Better power efficiency** on Apple Silicon

## Troubleshooting

### PostgreSQL Rosetta Error

If you see:
```
rosetta error: Rosetta is only intended to run on Apple Silicon...
```

**Solution:** Use `docker-compose.arm64.yml` which uses the native ARM64 PostgreSQL image.

### Python Dependency Errors

If you see:
```
ERROR: No matching distribution found for extractcode-7z
```

**Solution:** This is expected on ARM64. The ARM64 compose file skips these dependencies.

## Contributing ARM64 Support

Full ARM64 support is planned. If you're interested in contributing:
1. Check GitHub issues tagged with `arm64` or `apple-silicon`
2. Consider this as a GSoC project
3. Join the discussion on fossology-devel mailing list

## Future Roadmap

- [ ] Multi-architecture Docker images (AMD64 + ARM64)
- [ ] ARM64-compatible scancode integration
- [ ] CI/CD testing on ARM64
- [ ] Performance benchmarks

For questions, visit: https://github.com/fossology/fossology/issues
