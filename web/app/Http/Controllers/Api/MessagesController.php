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
     * Get all messages.
     */
    public function index(): JsonResponse
    {
        $messages = $this->messageService->getMessages();
        $unreadCount = $this->messageService->getUnreadCount();

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
}
