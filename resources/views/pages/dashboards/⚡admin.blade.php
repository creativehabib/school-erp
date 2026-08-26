<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Administration Dashboard')] class extends Component {};
?>

<x-dashboard.overview
    :eyebrow="auth()->user()->isSuperAdmin() ? __('Super Admin') : __('Admin')"
    :heading="__('Administration Dashboard')"
    :description="__('Manage academic operations, employees, accounts, documents, and institution-wide settings from one workspace.')"
    :metrics="[
        ['label' => __('Students'), 'value' => '—', 'detail' => __('Enrollment overview'), 'icon' => 'academic-cap'],
        ['label' => __('Employees'), 'value' => '—', 'detail' => __('HR and attendance'), 'icon' => 'user-group'],
        ['label' => __('Fee collection'), 'value' => '—', 'detail' => __('Accounts overview'), 'icon' => 'banknotes'],
        ['label' => __('Library issues'), 'value' => '—', 'detail' => __('Current circulation'), 'icon' => 'book-open'],
    ]"
    :actions="[
        ['label' => __('Academic & Exams'), 'description' => __('Classes, attendance, marks, and results'), 'icon' => 'academic-cap'],
        ['label' => __('HRM & Payroll'), 'description' => __('Employees, leave, attendance, and salary'), 'icon' => 'users'],
        ['label' => __('Accounts'), 'description' => __('Fees, payments, expenses, and reporting'), 'icon' => 'calculator'],
        ['label' => __('Documents'), 'description' => __('ID cards, admit cards, and certificates'), 'icon' => 'document-text'],
        ['label' => __('Library'), 'description' => __('Inventory, issue, return, and fines'), 'icon' => 'book-open'],
        ['label' => __('System settings'), 'description' => __('Users, roles, branches, and preferences'), 'icon' => 'cog-6-tooth'],
    ]"
/>
