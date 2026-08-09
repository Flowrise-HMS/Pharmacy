<?php

use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Tests\TestCase;

uses(TestCase::class);

it('computes schedules when frequency is a MedicationFrequency enum', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);

    $result = $calculator->compute([
        'frequency' => MedicationFrequency::BID,
        'duration_days' => 3,
        'prn' => false,
        'course_started_at' => now(),
    ]);

    expect($result['total_administrations'])->toBe(6)
        ->and($result['schedule_summary'])->toContain('3 day(s)');
});

it('computes schedules when frequency is a string', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);

    $result = $calculator->compute([
        'frequency' => 'qd',
        'duration_days' => 2,
        'prn' => false,
        'course_started_at' => now(),
    ]);

    expect($result['total_administrations'])->toBe(2);
});

it('leaves total administrations unbounded for PRN without a max', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);

    $result = $calculator->compute([
        'frequency' => 'qd',
        'duration_days' => 2,
        'prn' => true,
        'course_started_at' => now(),
    ]);

    expect($result['total_administrations'])->toBeNull()
        ->and($result['schedule_summary'])->toContain('PRN');
});
