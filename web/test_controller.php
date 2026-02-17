<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\TerminalController;
use Illuminate\Http\Request;
use App\Services\Messaging\MessageService;

echo "Testing Mission Completion through Controller\n";
echo "=============================================\n\n";

// Create a fresh session
$sessionId = 'test-' . uniqid();
session()->setId($sessionId);
session()->start();

$controller = app(TerminalController::class);
$messageService = app(MessageService::class);

// Helper to execute command through controller
function execCommand($controller, $cmd, $context = []) {
    $request = Request::create('/api/terminal', 'POST', [
        'command' => $cmd,
        'context' => $context
    ]);

    $response = $controller->execute($request);
    $data = json_decode($response->getContent(), true);

    return $data;
}

// Complete tutorial
echo "1. Completing tutorial objectives...\n";
$context = [];

execCommand($controller, 'ls', $context);
$result = execCommand($controller, 'cd documents', $context);
$context['currentPath'] = '/home/user/documents';

execCommand($controller, 'ls', $context);
execCommand($controller, 'cat mission_briefing.txt', $context);

// Final command that should complete tutorial
echo "\n2. Executing 'mail' to complete tutorial...\n";
$result = execCommand($controller, 'mail', $context);

if (!empty($result['stateChanges']['gameEvents'])) {
    echo "   ✓ Game Events:\n";
    foreach ($result['stateChanges']['gameEvents'] as $event) {
        echo "     - Type: {$event['type']}\n";
        echo "     - Mission: {$event['data']['missionId']}\n";
        echo "     - Unlocks: {$event['data']['unlocks']}\n";
    }
}

if (!empty($result['stateChanges']['missionCompleted'])) {
    echo "   ✓ Mission completed flag set!\n";
}

// Check messages
echo "\n3. Checking injected messages...\n";
$messages = $messageService->getMessages();
echo "   Total messages: " . count($messages) . "\n\n";

// Find tutorial completion message (ID 7)
$completionMsg = array_filter($messages, fn($m) => $m['id'] === 7);
if (!empty($completionMsg)) {
    $msg = reset($completionMsg);
    echo "   ✅ Tutorial completion message found!\n";
    echo "   From: {$msg['from']} {$msg['avatar']}\n";
    echo "   Subject: {$msg['subject']}\n";
    echo "   Preview: {$msg['preview']}\n";
    echo "\n   Body:\n";
    echo "   " . str_replace("\n", "\n   ", $msg['body']) . "\n";
} else {
    echo "   ❌ Tutorial completion message NOT found!\n";
    echo "   Message IDs present: " . implode(', ', array_column($messages, 'id')) . "\n";
}

echo "\n✓ Test Complete!\n";
