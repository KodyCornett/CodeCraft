<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Messaging\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagesController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {}

    /**
     * Get all messages (or trash).
     */
    public function index(Request $request): JsonResponse
    {
        $view = $request->query('view', 'inbox');

        if ($view === 'trash') {
            $messages = $this->messageService->getDeletedMessages();
            $unreadCount = 0;
        } else {
            $messages = $this->messageService->getMessages();
            $unreadCount = $this->messageService->getUnreadCount();
        }

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Get a single message.
     */
    public function show(int $id): JsonResponse
    {
        $message = $this->messageService->getMessage($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'error' => 'Message not found',
            ], 404);
        }

        // Mark as read
        $this->messageService->markAsRead($id);

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Mark message as read.
     */
    public function markRead(int $id): JsonResponse
    {
        $this->messageService->markAsRead($id);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Delete a message (move to trash).
     */
    public function delete(int $id): JsonResponse
    {
        $this->messageService->markAsDeleted($id);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Restore a message from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $this->messageService->restoreMessage($id);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Send a message.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|string|max:50',
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'replyTo' => 'nullable|integer',
        ]);

        $result = $this->messageService->sendMessage(
            $validated['to'],
            $validated['subject'],
            $validated['body'],
            $validated['replyTo'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Get contacts for composing.
     */
    public function contacts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'contacts' => $this->messageService->getContacts(),
        ]);
    }

    /**
     * Get unread count (for notification badge).
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'count' => $this->messageService->getUnreadCount(),
        ]);
    }

    /**
     * Get job details for secure channel.
     */
    public function getJob(string $id): JsonResponse
    {
        $job = $this->messageService->getJob($id);

        if (!$job) {
            return response()->json([
                'success' => false,
                'error' => 'Job not found or expired.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'job' => $job,
        ]);
    }

    /**
     * Attempt to negotiate job payout.
     */
    public function negotiateJob(string $id): JsonResponse
    {
        $result = $this->messageService->negotiateJob($id);

        return response()->json($result);
    }

    /**
     * Accept a job and get verification puzzle.
     */
    public function acceptJob(string $id): JsonResponse
    {
        $result = $this->messageService->acceptJob($id);

        return response()->json($result);
    }

    /**
     * Verify puzzle answer.
     */
    public function verifyPuzzle(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:20',
        ]);

        $result = $this->messageService->verifyPuzzle($id, $validated['answer']);

        return response()->json($result);
    }

    /**
     * Decrypt an encrypted message.
     */
    public function decrypt(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'passcode' => 'required|string|max:20',
        ]);

        $result = $this->messageService->decryptMessage($id, $validated['passcode']);

        return response()->json($result);
    }
}
