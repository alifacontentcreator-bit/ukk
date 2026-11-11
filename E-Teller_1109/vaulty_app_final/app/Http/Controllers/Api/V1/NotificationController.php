<?php
/* Tujuan file: Controller ini menyediakan daftar notifikasi nasabah untuk aplikasi mobile,
   termasuk notifikasi permintaan mutasi yang berkas PDF-nya sudah siap diunduh. */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi milik nasabah yang sedang login.
     * Gunakan ?mark_read=1 untuk menandai semua notifikasi sudah dibaca.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'mark_read' => ['nullable', 'boolean'],
        ]);

        $userId = (int) $request->user()->id;

        $query = CustomerNotification::where('user_id', $userId);

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $page = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        if (!empty($filters['mark_read'])) {
            CustomerNotification::where('user_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $unreadCount = 0;
        }

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (CustomerNotification $notification) => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'transaction_id' => $notification->transaction_id,
                    'is_read' => $notification->read_at !== null,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }
}
