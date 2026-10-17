<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Exception thrown when an agent attempts to claim an unassigned ticket
 * that has already been claimed or assigned by a concurrent process.
 *
 * Renders as HTTP 409 Conflict with a user-friendly message.
 */
class TicketAlreadyAssignedException extends Exception
{
    protected $message = 'This ticket has already been claimed by another agent or assigned.';

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Conflict',
                'message' => $this->getMessage(),
            ], 409);
        }

        return back()->with('error', $this->getMessage());
    }
}
