#!/bin/bash

# Production build script with memory optimization
# This script helps prevent "JS heap out of memory" errors during Laravel Mix builds

echo "Starting production build with memory optimization..."

# Set Node.js memory options
export NODE_OPTIONS="--max-old-space-size=4096 --optimize-for-size"

# Clear any existing build cache
echo "Clearing build cache..."
rm -rf node_modules/.cache
rm -rf public/js/*
rm -rf public/css/*

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm ci --prefer-offline --no-audit
fi

# Run the production build
echo "Running production build..."
npm run production

echo "Production build completed!"