#!/bin/bash

SESSION="test-scan-$(date +%s)"

echo "=== Test 1: Scan from localhost ==="
echo "Expected: Should show only directly connected IP (45.33.32.1), no names"
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: $SESSION" \
  -d '{"command": "scan"}'
echo ""
echo ""

echo "=== Test 2: Scan a specific IP ==="
echo "Expected: Should reveal node name 'Public Relay'"
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: $SESSION" \
  -d '{"command": "scan 45.33.32.1"}'
echo ""
echo ""

echo "=== Test 3: Connect to Public Relay ==="
curl -s -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -H "X-Session-ID: $SESSION" \
  -d '{"command": "connect 45.33.32.1"}'
echo ""
echo ""

# Wait a moment
sleep 1

echo "=== Test 4: Get session status to find puzzle answer ==="
STATUS=$(curl -s -X GET http://localhost:8085/api/session/$SESSION/status)
echo "$STATUS"
echo ""
echo ""
