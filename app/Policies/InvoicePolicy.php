<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\RoleName;
use App\Models\Accounts\Invoice;
use App\Models\User;

/**
 * Reference policy showing the two-layer authorisation model used throughout:
 *
 *   Layer 1 — CAN THIS ROLE do this at all?   -> Spatie permission check
 *   Layer 2 — CAN THIS USER touch THIS ROW?   -> ownership / scoping check
 *
 * A Guardian holds accounts.invoice.view, but must only ever see invoices
 * belonging to their own children. Permissions alone cannot express that, which
 * is exactly why every student-scoped resource needs a policy and not just
 * middleware.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.invoice.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('accounts.invoice.view')) {
            return false;
        }

        if ($user->hasRole(RoleName::Guardian->value)) {
            return $user->guardian
                ?->students()
                ->whereKey($invoice->student_id)
                ->exists() ?? false;
        }

        if ($user->hasRole(RoleName::Student->value)) {
            return $user->student?->id === $invoice->student_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('accounts.invoice.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        // A paid or cancelled invoice is a closed financial record.
        return $user->can('accounts.invoice.update')
            && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('accounts.invoice.cancel')
            && (float) $invoice->paid_total === 0.0;
    }

    public function collectPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('accounts.payment.collect') && $invoice->isPayable();
    }
}
