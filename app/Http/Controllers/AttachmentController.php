<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Download the specified ticket attachment.
     */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $ticket = $attachment->ticket;

        // Strict authorization:
        // A customer can only download attachments from their own tickets
        if (auth('customer')->check()) {
            abort_unless($ticket->customer_id === auth('customer')->id(), 403);
        } elseif (!auth('agent')->check() && !auth('admin')->check()) {
            abort(403);
        }

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }
}
