<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\Customer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy enforcing secure access to ticket attachments.
 *
 * Enforces Section 10 rules:
 * - Allowed only if actor can view the parent ticket
 * - Files attached to internal notes are STRICTLY forbidden for customers (staff only)
 */
class AttachmentPolicy
{
    /**
     * Determine whether the actor can download the attachment.
     */
    public function download(Authenticatable|Model $actor, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket;

        if (!$ticket) {
            return false;
        }

        // Customer access rules
        if ($actor instanceof Customer) {
            // Must be the owner of the ticket
            if ($ticket->customer_id !== $actor->id) {
                return false;
            }

            // Internal notes attachments are strictly hidden from customers
            if ($attachment->reply && $attachment->reply->is_internal) {
                return false;
            }

            return true;
        }

        // Agent access rules: assigned to ticket or unassigned triage pool
        if ($actor instanceof Agent) {
            return $ticket->assigned_agent_id === $actor->id || is_null($ticket->assigned_agent_id);
        }

        // Admin has global access to all ticket attachments
        if ($actor instanceof Admin) {
            return true;
        }

        return false;
    }
}
