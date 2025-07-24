# Laravel Mix Memory Fix for Railway Deployment

## Problem
When deploying to Railway, the Laravel Mix production build fails with:
```
Error: Worker terminated due to reaching memory limit: JS heap out of memory
```

## Root Cause
Node.js has default memory limits that are insufficient for large Laravel Mix builds with many assets and dependencies. The worker processes spawned by webpack hit the memory limit during the production build process.

## Solutions Implemented

### 1. Package.json Script Modification
Modified the production scripts in `package.json` to include Node.js memory options:

```json
{
  "scripts": {
    "prod": "NODE_OPTIONS=\"--max-old-space-size=4096\" npm run production",
    "production": "NODE_OPTIONS=\"--max-old-space-size=4096\" mix --production"
  }
}
```

### 2. Webpack Configuration Optimization
Enhanced `webpack.mix.js` with memory-efficient settings:

- **Code Splitting**: Separates vendor libraries from application code
- **Performance Hints**: Disabled to reduce memory overhead
- **CSS URL Processing**: Disabled to reduce processing load

### 3. Build Script Alternative
Created `build-production.sh` script with additional optimizations:

- Clears build caches before building
- Sets multiple Node.js memory options
- Provides verbose logging

## Usage Options

### Option 1: Use Modified npm Scripts (Recommended)
```bash
npm run prod
```

### Option 2: Use Build Script
```bash
./build-production.sh
```

### Option 3: Set Environment Variable
For Railway deployment, you can also set the environment variable:
```
NODE_OPTIONS=--max-old-space-size=4096
```

## Memory Settings Explanation

- `--max-old-space-size=4096`: Increases the V8 old memory space to 4GB
- `--optimize-for-size`: Optimizes V8 for memory usage over speed

## Railway Deployment
When deploying to Railway, the modified `package.json` scripts will automatically apply the memory fixes. No additional configuration is needed.

## Troubleshooting

If you still encounter memory issues:

1. **Increase memory limit**: Change `4096` to `6144` or `8192`
2. **Clear caches**: Delete `node_modules/.cache` and `public/js`, `public/css` directories
3. **Use the build script**: Run `./build-production.sh` instead of npm scripts
4. **Split builds**: Consider building assets in smaller chunks

## System Requirements
- Minimum 4GB RAM available
- Node.js v18+ (current version: v22.16.0)
- Sufficient disk space for build artifacts