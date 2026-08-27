<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Guardian Dashboard')] class extends Component {};
?>

<x-dashboard.overview
    :eyebrow="__('Guardian')"
    :heading="__('Guardian Dashboard')"
    :description="__('Monitor your children’s attendance, academic progress, examination results, and fee payment status.')"
    :metrics="[
        ['label' => __('Children'), 'value' => '—', 'detail' => __('Linked students'), 'icon' => 'user-group'],
        ['label' => __('Attendance'), 'value' => '—', 'detail' => __('Current session'), 'icon' => 'chart-bar'],
        ['label' => __('Fee due'), 'value' => '—', 'detail' => __('Outstanding balance'), 'icon' => 'banknotes'],
        ['label' => __('Latest result'), 'value' => '—', 'detail' => __('Published examination'), 'icon' => 'document-chart-bar'],
    ]"
    :actions="[
        ['label' => __('Attendance details'), 'description' => __('Review daily attendance records'), 'icon' => 'clipboard-document-check'],
        ['label' => __('Academic progress'), 'description' => __('Review results and marksheets'), 'icon' => 'chart-bar'],
        ['label' => __('Fee status'), 'description' => __('Review invoices and payments'), 'icon' => 'banknotes'],
    ]"
/>
