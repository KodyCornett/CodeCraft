# 🌐 LARAVEL UI TECHNICAL SPECIFICATION
## Urban Network Exploration System

**Target:** Laravel Backend + Alpine.js Frontend (`web/`)
**Purpose:** UI rendering, API integration, map visualization

---

## 📐 ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL BACKEND                           │
├─────────────────────────────────────────────────────────────┤
│  Controllers (API)                                           │
│  - NetworkController (scan, map, route, state)              │
│  - TerminalController (command passthrough)                  │
├─────────────────────────────────────────────────────────────┤
│  Services                                                    │
│  - KotlinGameEngine (HTTP client)                           │
│  - NetworkMapService (data formatting)                       │
├─────────────────────────────────────────────────────────────┤
│  Blade Views                                                 │
│  - desktop/windows/node-manager.blade.php                   │
└─────────────────────────────────────────────────────────────┘
                            ↓ JSON API
┌─────────────────────────────────────────────────────────────┐
│                  FRONTEND (Alpine.js)                        │
├─────────────────────────────────────────────────────────────┤
│  JavaScript Modules                                          │
│  - node-manager.js (canvas rendering)                        │
│  - terminal.js (command integration)                         │
│  - network-map-renderer.js (visualization)                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 FILE STRUCTURE

```
web/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── NetworkController.php      (new)
│   │           └── TerminalController.php     (updated)
│   │
│   └── Services/
│       ├── GameEngine/
│       │   └── KotlinGameEngine.php           (updated)
│       │
│       └── Network/
│           ├── NetworkMapService.php          (new)
│           └── NodeVisualizer.php             (new)
│
├── resources/
│   ├── js/
│   │   └── desktop/
│   │       ├── node-manager.js                (updated)
│   │       ├── network-map-renderer.js        (new)
│   │       ├── terminal.js                    (updated)
│   │       └── network-state-manager.js       (new)
│   │
│   └── views/
│       └── desktop/
│           └── windows/
│               └── node-manager.blade.php     (updated)
│
└── routes/
    └── web.php                                 (updated)
```

---

## 🎮 LARAVEL CONTROLLERS

### NetworkController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameEngine\KotlinGameEngine;
use App\Services\Network\NetworkMapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NetworkController extends Controller
{
    public function __construct(
        private readonly KotlinGameEngine $engine,
        private readonly NetworkMapService $mapService
    ) {}

    /**
     * Get current network state (for UI rendering)
     */
    public function getState(Request $request): JsonResponse
    {
        $sessionId = session()->getId();

        $state = $this->engine->getNetworkState($sessionId);

        return response()->json([
            'success' => true,
            'currentNode' => $state['currentNode'] ?? null,
            'discoveredNodes' => $state['discoveredNodes'] ?? [],
            'playerPosition' => $state['playerPosition'] ?? null,
        ]);
    }

    /**
     * Scan for nearby nodes (proxies to Kotlin engine)
     */
    public function scan(Request $request): JsonResponse
    {
        $sessionId = session()->getId();
        $range = $request->input('range');

        $result = $this->engine->scan($sessionId, $range);

        return response()->json([
            'success' => $result['success'],
            'output' => $result['output'],
            'newNodesDiscovered' => $result['newNodesDiscovered'] ?? 0,
        ]);
    }

    /**
     * Get map data (formatted for UI display)
     */
    public function getMap(Request $request): JsonResponse
    {
        $sessionId = session()->getId();
        $filter = $request->query('filter');
        $sort = $request->query('sort');

        $mapData = $this->engine->getMap($sessionId, $filter, $sort);

        // Format for frontend consumption
        $formatted = $this->mapService->formatMapData($mapData);

        return response()->json([
            'success' => true,
            'nodes' => $formatted['nodes'],
            'connections' => $formatted['connections'],
            'currentNode' => $formatted['currentNode'],
            'bounds' => $formatted['bounds'],
        ]);
    }

    /**
     * Calculate route between nodes
     */
    public function calculateRoute(Request $request): JsonResponse
    {
        $sessionId = session()->getId();
        $targetNodeName = $request->input('targetNodeName');

        $result = $this->engine->calculateRoute($sessionId, $targetNodeName);

        return response()->json([
            'success' => $result['success'],
            'path' => $result['path'] ?? [],
            'hops' => $result['hops'] ?? 0,
            'distance' => $result['distance'] ?? 0,
            'risk' => $result['risk'] ?? 0,
        ]);
    }
}
```

---

## 🔌 KOTLIN ENGINE CLIENT (Updated)

### KotlinGameEngine.php (Additions)
```php
<?php

namespace App\Services\GameEngine;

use Illuminate\Support\Facades\Http;

class KotlinGameEngine implements GameEngineInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('game.engine_url', 'http://127.0.0.1:8085');
    }

    /**
     * Get network state from Kotlin engine
     */
    public function getNetworkState(string $sessionId): array
    {
        $response = Http::get("{$this->baseUrl}/api/network/state/{$sessionId}");

        if (!$response->successful()) {
            return [
                'currentNode' => null,
                'discoveredNodes' => [],
                'playerPosition' => null,
            ];
        }

        return $response->json();
    }

    /**
     * Execute scan command
     */
    public function scan(string $sessionId, ?int $range = null): array
    {
        $response = Http::post("{$this->baseUrl}/api/network/scan/{$sessionId}", [
            'range' => $range,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'output' => 'ERROR: Scan failed',
            ];
        }

        return $response->json();
    }

    /**
     * Get map data
     */
    public function getMap(string $sessionId, ?string $filter = null, ?string $sort = null): array
    {
        $query = array_filter([
            'filter' => $filter,
            'sort' => $sort,
        ]);

        $response = Http::get("{$this->baseUrl}/api/network/map/{$sessionId}", $query);

        if (!$response->successful()) {
            return [
                'nodes' => [],
                'connections' => [],
            ];
        }

        return $response->json();
    }

    /**
     * Calculate route
     */
    public function calculateRoute(string $sessionId, string $targetNodeName): array
    {
        $response = Http::post("{$this->baseUrl}/api/network/route/{$sessionId}", [
            'targetNodeName' => $targetNodeName,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'path' => [],
            ];
        }

        return $response->json();
    }
}
```

---

## 🎨 SERVICES LAYER

### NetworkMapService.php (New)
```php
<?php

namespace App\Services\Network;

class NetworkMapService
{
    /**
     * Format raw map data for frontend rendering
     */
    public function formatMapData(array $rawData): array
    {
        $nodes = $rawData['discoveredNodes'] ?? [];
        $currentNode = $rawData['currentNode'] ?? null;

        // Calculate bounds for canvas sizing
        $bounds = $this->calculateBounds($nodes);

        // Format nodes for rendering
        $formattedNodes = array_map(function ($nodeData) use ($currentNode) {
            $node = $nodeData['node'];
            $state = $nodeData['state'];

            return [
                'id' => $node['nodeId'],
                'name' => $node['nodeName'],
                'type' => $node['nodeType'],
                'x' => $node['coordX'],
                'y' => $node['coordY'],
                'ip' => $node['ipAddress'],
                'signal' => $node['signalStrength'],
                'security' => $node['securityLevel'],
                'isPublic' => $node['isPublic'],
                'state' => $state,
                'isCurrent' => $currentNode && $node['nodeId'] === $currentNode['nodeId'],
                'icon' => $this->getNodeIcon($node['nodeType']),
                'color' => $this->getNodeColor($node['securityLevel'], $state),
            ];
        }, $nodes);

        return [
            'nodes' => $formattedNodes,
            'connections' => [], // Will be populated in Phase 3
            'currentNode' => $currentNode,
            'bounds' => $bounds,
        ];
    }

    private function calculateBounds(array $nodes): array
    {
        if (empty($nodes)) {
            return ['minX' => 0, 'minY' => 0, 'maxX' => 5000, 'maxY' => 5000];
        }

        $coords = array_map(fn($n) => $n['node'], $nodes);

        return [
            'minX' => min(array_column($coords, 'coordX')) - 100,
            'minY' => min(array_column($coords, 'coordY')) - 100,
            'maxX' => max(array_column($coords, 'coordX')) + 100,
            'maxY' => max(array_column($coords, 'coordY')) + 100,
        ];
    }

    private function getNodeIcon(string $nodeType): string
    {
        return match($nodeType) {
            'CAFE', 'COFFEE_SHOP', 'DINER' => 'C',
            'BAR', 'ARCADE' => 'B',
            'LIBRARY', 'COMMUNITY_CENTER' => 'L',
            'PHARMACY', 'CLINIC', 'HOSPITAL' => 'M',
            'STORE', 'BODEGA', 'PAWN_SHOP' => 'S',
            'HOTEL', 'HOSTEL' => 'H',
            'WAREHOUSE', 'FACTORY', 'DATA_CENTER' => 'I',
            'OFFICE_BUILDING', 'TECH_STARTUP' => '!',
            'TRAFFIC_CONTROL', 'SECURITY_STATION' => 'G',
            'RESIDENTIAL' => 'R',
            default => '?',
        };
    }

    private function getNodeColor(int $securityLevel, string $state): string
    {
        // State takes precedence
        if ($state === 'COMPROMISED') {
            return '#9b59b6'; // Purple
        }
        if ($state === 'LOCKED') {
            return '#95a5a6'; // Gray
        }

        // Security level colors
        return match($securityLevel) {
            1 => '#2ecc71', // Green (easy)
            2 => '#f1c40f', // Yellow (medium)
            3 => '#e67e22', // Orange (hard)
            4, 5 => '#e74c3c', // Red (very hard)
            default => '#3498db', // Blue (unknown)
        };
    }
}
```

---

## 🎨 FRONTEND (Alpine.js)

### node-manager.js (Updated)
```javascript
// web/resources/js/desktop/node-manager.js

import axios from 'axios';
import { NetworkMapRenderer } from './network-map-renderer.js';

export function initNodeManager() {
    return {
        nodes: [],
        currentNode: null,
        mapRenderer: null,
        filter: null,
        sort: 'distance',
        loading: false,

        init() {
            this.mapRenderer = new NetworkMapRenderer(
                this.$refs.canvas,
                this.onNodeClick.bind(this)
            );
            this.loadNetworkState();
            this.startAutoRefresh();
        },

        async loadNetworkState() {
            this.loading = true;
            try {
                const response = await axios.get('/api/network/state');
                this.nodes = response.data.discoveredNodes || [];
                this.currentNode = response.data.currentNode;

                // Update map visualization
                const mapData = await axios.get('/api/network/map', {
                    params: {
                        filter: this.filter,
                        sort: this.sort,
                    },
                });

                this.mapRenderer.render(
                    mapData.data.nodes,
                    mapData.data.connections,
                    mapData.data.currentNode,
                    mapData.data.bounds
                );
            } catch (error) {
                console.error('Failed to load network state:', error);
            } finally {
                this.loading = false;
            }
        },

        async scanNetwork() {
            this.loading = true;
            try {
                const response = await axios.post('/api/network/scan');
                if (response.data.success) {
                    // Refresh map after scan
                    await this.loadNetworkState();

                    // Show notification
                    this.showNotification(
                        `Scan complete. ${response.data.newNodesDiscovered} new nodes discovered.`
                    );
                }
            } catch (error) {
                console.error('Scan failed:', error);
            } finally {
                this.loading = false;
            }
        },

        async connectToNode(nodeName) {
            try {
                const response = await axios.post('/api/terminal/command', {
                    command: `connect "${nodeName}"`,
                });

                if (response.data.success) {
                    await this.loadNetworkState();
                    this.showNotification(`Connected to ${nodeName}`);
                } else {
                    this.showNotification(response.data.output, 'error');
                }
            } catch (error) {
                console.error('Connection failed:', error);
            }
        },

        async calculateRoute(targetNodeName) {
            try {
                const response = await axios.post('/api/network/route', {
                    targetNodeName,
                });

                if (response.data.success) {
                    this.mapRenderer.highlightPath(response.data.path);
                    this.showNotification(
                        `Route found: ${response.data.hops} hops, ${response.data.distance}m, Risk: ${response.data.risk}`
                    );
                }
            } catch (error) {
                console.error('Route calculation failed:', error);
            }
        },

        onNodeClick(node) {
            // Show node details panel
            this.$dispatch('show-node-details', node);
        },

        startAutoRefresh() {
            setInterval(() => {
                if (!this.loading) {
                    this.loadNetworkState();
                }
            }, 10000); // Refresh every 10 seconds
        },

        showNotification(message, type = 'info') {
            this.$dispatch('show-notification', { message, type });
        },

        filterByType(type) {
            this.filter = type;
            this.loadNetworkState();
        },

        sortBy(sortType) {
            this.sort = sortType;
            this.loadNetworkState();
        },

        getStateLabel(state) {
            return {
                DISCOVERED: 'Never visited',
                CONNECTED: 'Connected',
                COMPROMISED: 'Compromised',
                LOCKED: 'Locked',
            }[state] || state;
        },

        getStateIcon(state) {
            return {
                DISCOVERED: '◇',
                CONNECTED: '◆',
                COMPROMISED: '✓',
                LOCKED: '✗',
            }[state] || '?';
        },
    };
}
```

### network-map-renderer.js (New)
```javascript
// web/resources/js/desktop/network-map-renderer.js

export class NetworkMapRenderer {
    constructor(canvas, onNodeClick) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.onNodeClick = onNodeClick;

        this.nodes = [];
        this.connections = [];
        this.currentNode = null;
        this.bounds = { minX: 0, minY: 0, maxX: 5000, maxY: 5000 };

        this.viewOffsetX = 0;
        this.viewOffsetY = 0;
        this.zoom = 1.0;

        this.setupEventListeners();
    }

    setupEventListeners() {
        this.canvas.addEventListener('click', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const clickedNode = this.getNodeAtPosition(x, y);
            if (clickedNode && this.onNodeClick) {
                this.onNodeClick(clickedNode);
            }
        });

        // Pan and zoom controls (optional)
        this.canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.9 : 1.1;
            this.zoom = Math.max(0.5, Math.min(2.0, this.zoom * delta));
            this.render(this.nodes, this.connections, this.currentNode, this.bounds);
        });
    }

    render(nodes, connections, currentNode, bounds) {
        this.nodes = nodes;
        this.connections = connections;
        this.currentNode = currentNode;
        this.bounds = bounds;

        this.clear();
        this.drawGrid();
        this.drawConnections();
        this.drawNodes();
    }

    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.fillStyle = '#1a1a1a';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    }

    drawGrid() {
        const gridSize = 100;
        this.ctx.strokeStyle = '#2a2a2a';
        this.ctx.lineWidth = 1;

        for (let x = 0; x < this.canvas.width; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, this.canvas.height);
            this.ctx.stroke();
        }

        for (let y = 0; y < this.canvas.height; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(this.canvas.width, y);
            this.ctx.stroke();
        }
    }

    drawConnections() {
        this.connections.forEach((conn) => {
            const fromPos = this.worldToScreen(conn.from.x, conn.from.y);
            const toPos = this.worldToScreen(conn.to.x, conn.to.y);

            this.ctx.strokeStyle = '#444';
            this.ctx.lineWidth = 1;
            this.ctx.beginPath();
            this.ctx.moveTo(fromPos.x, fromPos.y);
            this.ctx.lineTo(toPos.x, toPos.y);
            this.ctx.stroke();
        });
    }

    drawNodes() {
        this.nodes.forEach((node) => {
            const pos = this.worldToScreen(node.x, node.y);

            // Draw node circle
            this.ctx.fillStyle = node.color;
            this.ctx.beginPath();
            this.ctx.arc(pos.x, pos.y, 8, 0, 2 * Math.PI);
            this.ctx.fill();

            // Highlight current node
            if (node.isCurrent) {
                this.ctx.strokeStyle = '#fff';
                this.ctx.lineWidth = 2;
                this.ctx.stroke();
            }

            // Draw node icon
            this.ctx.fillStyle = '#fff';
            this.ctx.font = '12px monospace';
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            this.ctx.fillText(node.icon, pos.x, pos.y);

            // Draw node name
            this.ctx.fillStyle = '#ccc';
            this.ctx.font = '10px monospace';
            this.ctx.fillText(node.name, pos.x, pos.y + 15);
        });
    }

    worldToScreen(worldX, worldY) {
        const scaleX = this.canvas.width / (this.bounds.maxX - this.bounds.minX);
        const scaleY = this.canvas.height / (this.bounds.maxY - this.bounds.minY);

        return {
            x: (worldX - this.bounds.minX) * scaleX * this.zoom + this.viewOffsetX,
            y: (worldY - this.bounds.minY) * scaleY * this.zoom + this.viewOffsetY,
        };
    }

    getNodeAtPosition(screenX, screenY) {
        const threshold = 15; // pixels
        return this.nodes.find((node) => {
            const pos = this.worldToScreen(node.x, node.y);
            const distance = Math.sqrt(
                Math.pow(pos.x - screenX, 2) + Math.pow(pos.y - screenY, 2)
            );
            return distance <= threshold;
        });
    }

    highlightPath(path) {
        // Highlight a path on the map (for route command)
        path.forEach((nodeName, index) => {
            const node = this.nodes.find((n) => n.name === nodeName);
            if (node && index < path.length - 1) {
                const nextNode = this.nodes.find((n) => n.name === path[index + 1]);
                if (nextNode) {
                    const fromPos = this.worldToScreen(node.x, node.y);
                    const toPos = this.worldToScreen(nextNode.x, nextNode.y);

                    this.ctx.strokeStyle = '#f1c40f';
                    this.ctx.lineWidth = 3;
                    this.ctx.beginPath();
                    this.ctx.moveTo(fromPos.x, fromPos.y);
                    this.ctx.lineTo(toPos.x, toPos.y);
                    this.ctx.stroke();
                }
            }
        });
    }
}
```

---

## 🗺️ BLADE VIEW

### node-manager.blade.php (Updated)
```blade
<div x-data="initNodeManager()" class="window-content h-full flex flex-col bg-gray-900 text-green-400 font-mono">
    <!-- Toolbar -->
    <div class="flex items-center justify-between p-2 border-b border-gray-700">
        <div class="flex items-center gap-2">
            <button @click="scanNetwork" class="px-3 py-1 bg-green-700 hover:bg-green-600 rounded text-sm">
                <i class="fas fa-radar"></i> Scan
            </button>

            <select x-model="filter" @change="loadNetworkState" class="px-2 py-1 bg-gray-800 border border-gray-700 rounded text-sm">
                <option :value="null">All Nodes</option>
                <option value="public">Public Only</option>
                <option value="secure">Secure Only</option>
                <option value="connected">Visited</option>
            </select>

            <select x-model="sort" @change="loadNetworkState" class="px-2 py-1 bg-gray-800 border border-gray-700 rounded text-sm">
                <option value="distance">Sort by Distance</option>
                <option value="name">Sort by Name</option>
                <option value="signal">Sort by Signal</option>
            </select>
        </div>

        <div class="text-sm">
            <span x-text="nodes.length"></span> nodes discovered
        </div>
    </div>

    <!-- Map Canvas -->
    <div class="flex-1 relative overflow-hidden">
        <canvas
            x-ref="canvas"
            width="800"
            height="600"
            class="w-full h-full"
            style="image-rendering: crisp-edges;"
        ></canvas>

        <!-- Loading overlay -->
        <div x-show="loading" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="text-white text-lg">SCANNING...</div>
        </div>
    </div>

    <!-- Node List (sidebar) -->
    <div class="w-64 border-l border-gray-700 p-2 overflow-y-auto">
        <div class="text-sm font-bold mb-2">DISCOVERED NODES</div>

        <template x-for="node in nodes" :key="node.id">
            <div
                @click="connectToNode(node.name)"
                class="p-2 mb-1 bg-gray-800 hover:bg-gray-700 cursor-pointer rounded text-xs"
                :class="{ 'ring-2 ring-green-500': node.isCurrent }"
            >
                <div class="flex items-center justify-between">
                    <span class="font-bold" x-text="getStateIcon(node.state)"></span>
                    <span x-text="node.icon" class="font-mono"></span>
                </div>
                <div class="truncate" x-text="node.name"></div>
                <div class="text-gray-500" x-text="getStateLabel(node.state)"></div>
            </div>
        </template>
    </div>
</div>

<script>
    import { initNodeManager } from '@/desktop/node-manager.js';
    window.initNodeManager = initNodeManager;
</script>
```

---

## 🛣️ ROUTES

### web.php (Additions)
```php
// Network API routes
Route::prefix('api/network')->group(function () {
    Route::get('/state', [NetworkController::class, 'getState']);
    Route::get('/map', [NetworkController::class, 'getMap']);
    Route::post('/scan', [NetworkController::class, 'scan']);
    Route::post('/route', [NetworkController::class, 'calculateRoute']);
});
```

---

## 🧪 TESTING

### Feature Tests
```php
// tests/Feature/NetworkControllerTest.php

class NetworkControllerTest extends TestCase
{
    public function test_get_network_state()
    {
        $response = $this->get('/api/network/state');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'currentNode',
            'discoveredNodes',
            'playerPosition',
        ]);
    }

    public function test_scan_returns_discovered_nodes()
    {
        $response = $this->post('/api/network/scan', [
            'range' => 500,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
```

---

## 🎨 STYLING CONSIDERATIONS

### Tailwind CSS Classes
- Use monospace fonts for terminal aesthetic
- Dark theme with green/cyan accents
- Highlight current node with border/ring
- Use color coding for security levels
- Smooth transitions for interactions

---

**Last Updated:** 2026-02-16
**Status:** Specification Complete
**Version:** 1.0
