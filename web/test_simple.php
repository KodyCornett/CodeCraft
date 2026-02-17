<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\Messaging\MessageService;

$engine = app(GameEngineInterface::class);
$messageService = app(MessageService::class);

// Create a fresh test session
$sessionId = 'test-' . uniqid();
session()->setId($sessionId);
session()->start();

echo "Testing Tutorial Completion with New Engine\n";
echo "===========================================\n\n";

// Complete tutorial
$commands = ['ls', 'cd documents', 'ls', 'cat mission_briefing.txt'];

foreach ($commands as $cmd) {
    echo "Executing: $cmd\n";
    $result = $engine->executeCommand($sessionId, $cmd, []);
}

// Final command to trigger completion
echo "\nExecuting final mail command...\n";
$result = $engine->executeCommand($sessionId, 'mail', []);

echo "\nCommand Result:\n";
echo "  Success: " . ($result->success ? 'yes' : 'no') . "\n";
echo "  State Changes Keys: " . implode(', ', array_keys($result->stateChanges ?? [])) . "\n";

if (!empty($result->stateChanges['gameEvents'])) {
    echo "\n✓ Game Events Found:\n";
    foreach ($result->stateChanges['gameEvents'] as $event) {
        echo "  Type: {$event['type']}\n";
        echo "  Data: " . json_encode($event['data'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "\n⚠ No game events in result\n";
}

// Check messages
echo "\n\nMessages in system:\n";
$messages = $messageService->getMessages();

foreach ($messages as $msg) {
    if ($msg['id'] >= 7) {  // Only show new messages (tutorial completion)
        echo "\n  ID: {$msg['id']}\n";
        echo "  From: {$msg['from']} {$msg['avatar']}\n";
        echo "  Subject: {$msg['subject']}\n";
        echo "  Body:\n";
        echo "  " . str_replace("\n", "\n  ", $msg['body']) . "\n";
    }
}

echo "\n✓ Test Complete\n";
