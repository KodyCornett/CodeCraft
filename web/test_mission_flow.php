<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\Messaging\MessageService;

echo "=== Testing Mission Progression Notification System ===\n\n";

$engine = app(GameEngineInterface::class);
$messageService = app(MessageService::class);

// Create a test session
$sessionId = 'test-' . time();
session()->setId($sessionId);
session()->start();

echo "1. Starting tutorial mission...\n";

// Execute tutorial commands
$commands = ['ls', 'cd documents', 'cat mission_briefing.txt', 'mail'];

foreach ($commands as $cmd) {
    echo "   Executing: $cmd\n";
    $result = $engine->executeCommand($sessionId, $cmd, []);

    if (!$result->success) {
        echo "   ERROR: {$result->output}\n";
    }

    // Check for game events
    if (!empty($result->stateChanges['gameEvents'])) {
        echo "   ✓ Game event detected!\n";
        foreach ($result->stateChanges['gameEvents'] as $event) {
            echo "     - Type: {$event['type']}\n";
            echo "     - Mission: {$event['data']['missionId']}\n";
            echo "     - Unlocks: {$event['data']['unlocks']}\n";
        }
    }
}

echo "\n2. Checking injected messages...\n";

// Get messages
$messages = $messageService->getMessages();
echo "   Total messages: " . count($messages) . "\n\n";

if (!empty($messages)) {
    foreach ($messages as $msg) {
        echo "   Message #{$msg['id']}\n";
        echo "   From: {$msg['from']} {$msg['avatar']}\n";
        echo "   Subject: {$msg['subject']}\n";
        echo "   Preview: {$msg['preview']}\n";
        echo "   ---\n";
    }
} else {
    echo "   ⚠ No messages found!\n";
}

echo "\n3. Testing mission_1 completion...\n";

// Accept mission_1
echo "   Accepting mission_1...\n";
$result = $engine->executeCommand($sessionId, 'job accept mission_1', []);
echo "   " . ($result->success ? "✓" : "✗") . " {$result->output}\n";

// Complete mission_1 objectives
echo "   Completing objectives...\n";
$missionCommands = [
    'scan',
    'connect nova-corp-web',
    'ls',
    'cd data',
    'download credentials.dat',
    'analyze credentials.dat',
    'submit ACCESS GRANTED SECURE'
];

foreach ($missionCommands as $cmd) {
    echo "   Executing: $cmd\n";
    $result = $engine->executeCommand($sessionId, $cmd, []);

    if (!$result->success) {
        echo "     ✗ Failed: {$result->output}\n";
    } else {
        echo "     ✓ Success\n";
    }

    // Show objectives completed
    if (!empty($result->stateChanges['objectivesCompleted'])) {
        echo "     → Objectives completed: " . implode(', ', $result->stateChanges['objectivesCompleted']) . "\n";
    }

    if (!empty($result->stateChanges['gameEvents'])) {
        echo "   🎉 Mission completed event detected!\n";
        foreach ($result->stateChanges['gameEvents'] as $event) {
            if ($event['type'] === 'mission_completed') {
                echo "     - Completed: {$event['data']['missionId']}\n";
                $unlocks = $event['data']['unlocks'] ?? '';
                echo "     - Unlocks: $unlocks\n";

                // Parse unlocks
                $unlockedMissions = !empty($unlocks) ? explode(',', $unlocks) : [];
                echo "     - Unlocked missions: " . implode(', ', $unlockedMissions) . "\n";
            }
        }
    }
}

echo "\n4. Checking new messages after mission_1...\n";

$messages = $messageService->getMessages();
echo "   Total messages: " . count($messages) . "\n\n";

$newMessages = array_filter($messages, fn($m) => $m['id'] >= 1001);
if (!empty($newMessages)) {
    foreach ($newMessages as $msg) {
        echo "   Message #{$msg['id']}\n";
        echo "   From: {$msg['from']} {$msg['avatar']}\n";
        echo "   Subject: {$msg['subject']}\n";
        echo "   Preview: {$msg['preview']}\n";
        echo "   ---\n";
    }
} else {
    echo "   ⚠ No new completion/unlock messages found!\n";
}

echo "\n=== Test Complete ===\n";
