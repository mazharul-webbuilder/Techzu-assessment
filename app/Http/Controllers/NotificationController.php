<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Get the active user across customer, agent, or admin guards.
     */
    protected function getActiveUser(): ?object
    {
        return auth('admin')->user()
            ?? auth('agent')->user()
            ?? auth('customer')->user();
    }

    /**
     * Fetch unread and recent notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getActiveUser();

        if (!$user) {
            return response()->json(['unread_count' => 0, 'notifications' => []], 401);
        }

        $notifications = $user->notifications()
            ->latest()
            ->take(15)
            ->get()
            ->map(function (DatabaseNotification $n) {
                return [
                    'id' => $n->id,
                    'read' => $n->read(),
                    'title' => $n->data['title'] ?? 'Ticket Notification',
                    'message' => $n->data['message'] ?? '',
                    'ticket_number' => $n->data['ticket_number'] ?? null,
                    'url' => $n->data['url'] ?? '#',
                    'created_at' => $n->created_at->diffForHumans(),
                    'type' => $n->data['type'] ?? 'info',
                ];
            });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a specific notification as read and redirect to its target URL.
     */
    public function read(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $user = $this->getActiveUser();

        if (!$user) {
            return redirect()->back();
        }

        $notification = $user->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            $url = $notification->data['url'] ?? null;
            if ($url && !$request->wantsJson()) {
                return redirect()->to($url);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Mark all notifications as read for the active user.
     */
    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->getActiveUser();

        if ($user) {
            $user->unreadNotifications->markAsRead();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
