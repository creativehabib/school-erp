<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Teacher Dashboard')] class extends Component {};
?>

<x-dashboard.overview
    :eyebrow="__('Teacher')"
    :heading="__('Teacher Dashboard')"
    :description="__('Access assigned classes, record attendance and marks, and follow today’s academic schedule.')"
    :metrics="[
        ['label' => __('Assigned classes'), 'value' => '—', 'detail' => __('Current session'), 'icon' => 'academic-cap'],
        ['label' => __('Today’s periods'), 'value' => '—', 'detail' => __('Class routine'), 'icon' => 'clock'],
        ['label' => __('Attendance'), 'value' => '—', 'detail' => __('Pending registers'), 'icon' => 'clipboard-document-check'],
        ['label' => __('Marks entry'), 'value' => '—', 'detail' => __('Open examinations'), 'icon' => 'pencil-square'],
    ]"
    :actions="[
        ['label' => __('Record attendance'), 'description' => __('Take attendance for an assigned section'), 'icon' => 'clipboard-document-check'],
        ['label' => __('Enter marks'), 'description' => __('Record marks for an active examination'), 'icon' => 'pencil-square'],
        ['label' => __('View routine'), 'description' => __('Review the weekly class schedule'), 'icon' => 'calendar-days'],
    ]"
/>
