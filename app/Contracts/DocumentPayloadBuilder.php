<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Turns a domain record into the flat, primitive array a template renders from.
 *
 * The indirection earns its keep twice.
 *
 * First, SNAPSHOTTING. Whatever this returns is stored verbatim on
 * generated_documents.payload. A transfer certificate issued in 2026 must reprint in 2031
 * with the class, roll and dates it had in 2026, even after the student's record has been
 * edited, the class renamed and the fee structure replaced. Rendering directly from live
 * Eloquent models would quietly reprint a different document under the same serial number.
 *
 * Second, SANDBOXING. Administrator-authored templates resolve placeholders against this
 * array, so the template language never touches a model and cannot reach a relationship,
 * a scope, or a raw query. What the builder does not put in the array does not exist as
 * far as a template is concerned.
 */
interface DocumentPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $context  Extra selections from the operator: the
     *                                         exam for an admit card, the month for an
     *                                         attendance sheet, free-text remarks.
     * @return array<string, mixed>
     */
    public function build(Model $subject, array $context = []): array;

    /**
     * Model classes this builder accepts.
     *
     * Checked before building so a mis-wired batch fails loudly at the first record
     * rather than producing 300 certificates with blank names.
     *
     * @return array<int, class-string<Model>>
     */
    public function accepts(): array;
}
