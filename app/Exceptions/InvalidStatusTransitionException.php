<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Exception thrown when attempting an illegal ticket status transition
 * according to the state machine defined in TicketStatus::canTransitionTo().
 */
class InvalidStatusTransitionException extends Exception
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unprocessable Content',
                'message' => $this->getMessage(),
            ], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
