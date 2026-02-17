#!/bin/bash

# Test progressive network discovery

echo "=== Test 1: Scan from localhost ==="
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: test-progressive-scan" \
  -d '{"command": "scan"}' | jq -r '.output'

echo ""
echo "=== Test 2: Connect to first discovered node ==="
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: test-progressive-scan" \
  -d '{"command": "connect 45.33.32.1"}' | jq -r '.output'

echo ""
echo "=== Test 3: Solve puzzle (if needed) ==="
# Get puzzle answer first
PUZZLE=$(curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: test-progressive-scan" \
  -d '{"command": "status"}' | jq -r '.state.pendingPuzzle.answer')

if [ "$PUZZLE" != "null" ]; then
  echo "Solving puzzle with answer: $PUZZLE"
  curl -s -X POST http://localhost:8085/api/command \
    -H "Content-Type: application/json" \
    -H "X-Session-ID: test-progressive-scan" \
    -d "{\"command\": \"solve $PUZZLE\"}" | jq -r '.output'
fi

echo ""
echo "=== Test 4: Scan from connected node (should show different nodes) ==="
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: test-progressive-scan" \
  -d '{"command": "scan"}' | jq -r '.output'

echo ""
echo "=== Test 5: Port scan a specific IP ==="
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: test-progressive-scan" \
  -d '{"command": "scan 192.168.50.10"}' | jq -r '.output'
