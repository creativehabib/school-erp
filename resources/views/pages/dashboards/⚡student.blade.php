<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Student Dashboard')] class extends Component {};
?>

<x-dashboard.overview
    :eyebrow="__('Student')"
    :heading="__('Student Dashboard')"
    :description="__('Keep track of attendance, examinations, results, fees, notices, and learning resources.')"
    :metrics="[
        ['label' => __('Attendance'), 'value' => '—', 'detail' => __('Current session'), 'icon' => 'chart-bar'],
        ['label' => __('Next exam'), 'value' => '—', 'detail' => __('Published schedule'), 'icon' => 'calendar-days'],
        ['label' => __('Fee due'), 'value' => '—', 'detail' => __('Outstanding balance'), 'icon' => 'banknotes'],
        ['label' => __('Library books'), 'value' => '—', 'detail' => __('Currently issued'), 'icon' => 'book-open'],
    ]"
    :actions="[
        ['label' => __('Class routine'), 'description' => __('View the weekly timetable'), 'icon' => 'calendar-days'],
        ['label' => __('Results'), 'description' => __('View published marksheets'), 'icon' => 'document-chart-bar'],
        ['label' => __('Study materials'), 'description' => __('Access shared learning resources'), 'icon' => 'book-open'],
    ]"
/>
