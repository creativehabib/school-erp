<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission naming convention: module.resource.action
 *
 *   accounts.invoice.create     hrm.payroll.approve     academic.mark.enter
 *
 * Three fixed segments, always singular resource, always lower snake. This
 * matters more than it looks: with ~150 permissions you need to be able to
 * grep, wildcard and reason about them. Ad-hoc names like "can_edit_fees" rot
 * within a month.
 *
 * Super Admin is NOT granted permissions here — it is short-circuited in
 * AppServiceProvider with a Gate::before, so a newly added permission is
 * automatically available to Super Admin without re-seeding:
 *
 *   Gate::before(fn ($user) => $user->hasRole(RoleName::SuperAdmin->value) ?: null);
 *
 * Return null, not false, or you will deny every other check.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * module => [resource => [actions]]
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $matrix = [
        'system' => [
            'user' => ['view', 'create', 'update', 'delete', 'impersonate'],
            'role' => ['view', 'create', 'update', 'delete'],
            'setting' => ['view', 'update'],
            'backup' => ['view', 'create'],
            'audit' => ['view'],
        ],
        'academic' => [
            'session' => ['view', 'create', 'update', 'delete', 'lock'],
            'class' => ['view', 'create', 'update', 'delete'],
            'section' => ['view', 'create', 'update', 'delete'],
            'shift' => ['view', 'create', 'update', 'delete'],
            'student' => ['view', 'create', 'update', 'delete', 'promote', 'export'],
            'guardian' => ['view', 'create', 'update', 'delete'],
            'attendance' => ['view', 'record', 'update', 'report'],
            'routine' => ['view', 'create', 'update', 'delete'],
            'exam' => ['view', 'create', 'update', 'delete', 'publish'],
            'mark' => ['view', 'enter', 'update', 'verify'],
            'marksheet' => ['view', 'generate'],
        ],
        'hrm' => [
            'employee' => ['view', 'create', 'update', 'delete', 'export'],
            'department' => ['view', 'create', 'update', 'delete'],
            'designation' => ['view', 'create', 'update', 'delete'],
            'attendance' => ['view', 'record', 'update', 'report'],
            'leave' => ['view', 'apply', 'approve', 'delete'],
            'leave_type' => ['view', 'create', 'update', 'delete'],
            'salary_component' => ['view', 'create', 'update', 'delete'],
            'salary_structure' => ['view', 'update'],
            'payroll' => ['view', 'generate', 'approve', 'disburse', 'lock', 'delete'],
            'payslip' => ['view', 'print'],
        ],
        'accounts' => [
            'fee_head' => ['view', 'create', 'update', 'delete'],
            'fee_structure' => ['view', 'create', 'update', 'delete'],
            'fee_waiver' => ['view', 'create', 'approve', 'delete'],
            'invoice' => ['view', 'create', 'generate', 'update', 'cancel', 'print'],
            'payment' => ['view', 'collect', 'refund', 'print'],
            'expense' => ['view', 'create', 'update', 'approve', 'delete'],
            'expense_category' => ['view', 'create', 'update', 'delete'],
            'financial_account' => ['view', 'create', 'update', 'delete'],
            'report' => ['view', 'export'],
        ],
        'document' => [
            'id_card' => ['generate'],
            'admit_card' => ['generate'],
            'testimonial' => ['generate'],
            'transfer_certificate' => ['generate'],
            'template' => ['view', 'create', 'update', 'delete'],
        ],
        'library' => [
            'book' => ['view', 'create', 'update', 'delete'],
            'category' => ['view', 'create', 'update', 'delete'],
            'issue' => ['view', 'create', 'return', 'delete'],
            'fine' => ['view', 'waive', 'collect'],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->flattenMatrix();

        // Bulk insert then sync — far faster than firstOrCreate in a loop
        // once you are past a hundred permissions.
        $now = now();

        DB::table('permissions')->upsert(
            array_map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ], $permissions),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions($this->permissionsFor($roleName, $permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<int, string> */
    private function flattenMatrix(): array
    {
        $permissions = [];

        foreach ($this->matrix as $module => $resources) {
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $permissions[] = "{$module}.{$resource}.{$action}";
                }
            }
        }

        return $permissions;
    }

    /**
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function permissionsFor(RoleName $role, array $all): array
    {
        return match ($role) {
            // Handled by Gate::before; kept empty so the role list stays honest.
            RoleName::SuperAdmin => [],

            // Everything except destructive system operations.
            RoleName::Admin => array_values(array_filter(
                $all,
                fn (string $p) => ! in_array($p, [
                    'system.user.impersonate',
                    'system.role.delete',
                    'system.backup.create',
                    'academic.session.lock',
                    'hrm.payroll.lock',
                ], true)
            )),

            RoleName::Teacher => [
                'academic.student.view',
                'academic.guardian.view',
                'academic.class.view',
                'academic.section.view',
                'academic.attendance.view',
                'academic.attendance.record',
                'academic.attendance.report',
                'academic.routine.view',
                'academic.exam.view',
                'academic.mark.view',
                'academic.mark.enter',
                'academic.mark.update',
                'academic.marksheet.view',
                'hrm.leave.view',
                'hrm.leave.apply',
                'hrm.payslip.view',
                'hrm.payslip.print',
                'library.book.view',
                'library.issue.view',
            ],

            RoleName::Student => [
                'academic.attendance.view',
                'academic.routine.view',
                'academic.exam.view',
                'academic.marksheet.view',
                'accounts.invoice.view',
                'accounts.invoice.print',
                'accounts.payment.view',
                'document.admit_card.generate',
                'document.id_card.generate',
                'library.book.view',
                'library.issue.view',
            ],

            RoleName::Guardian => [
                'academic.student.view',
                'academic.attendance.view',
                'academic.routine.view',
                'academic.exam.view',
                'academic.marksheet.view',
                'accounts.invoice.view',
                'accounts.invoice.print',
                'accounts.payment.view',
                'accounts.payment.collect',
                'document.admit_card.generate',
                'library.issue.view',
                'library.fine.view',
            ],
        };
    }
}
