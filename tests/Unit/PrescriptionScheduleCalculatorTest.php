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

it('recognizes British and Commonwealth clinical frequency aliases BD, OD, TDS, QDS', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);

    expect($calculator->compute(['frequency' => 'bd', 'duration_days' => 2])['total_administrations'])->toBe(4)
        ->and($calculator->compute(['frequency' => 'od', 'duration_days' => 3])['total_administrations'])->toBe(3)
        ->and($calculator->compute(['frequency' => 'tds', 'duration_days' => 2])['total_administrations'])->toBe(6)
        ->and($calculator->compute(['frequency' => 'qds', 'duration_days' => 1])['total_administrations'])->toBe(4);
});

it('builds automated interval dose slots starting from custom start time for BD and TDS', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);
    $startTime = now()->setTime(14, 0, 0);

    $detail = (object) [
        'frequency' => 'bd',
        'duration_days' => 2,
        'course_started_at' => $startTime->toDateTimeString(),
        'total_administrations' => 4,
        'schedule_type' => 'interval_based',
        'prn' => false,
    ];

    $slots = $calculator->buildDoseSchedule($detail);

    expect($slots)->toHaveCount(4)
        ->and($slots[0]->sequence)->toBe(1)
        ->and($slots[0]->dueAt->format('Y-m-d H:i'))->toBe($startTime->format('Y-m-d 14:00'))
        ->and($slots[1]->sequence)->toBe(2)
        ->and($slots[1]->dueAt->format('Y-m-d H:i'))->toBe($startTime->copy()->addHours(12)->format('Y-m-d 02:00'))
        ->and($slots[2]->sequence)->toBe(3)
        ->and($slots[2]->dueAt->format('Y-m-d H:i'))->toBe($startTime->copy()->addHours(24)->format('Y-m-d 14:00'))
        ->and($slots[3]->sequence)->toBe(4)
        ->and($slots[3]->dueAt->format('Y-m-d H:i'))->toBe($startTime->copy()->addHours(36)->format('Y-m-d 02:00'));
});

it('builds dose slots with custom daily times', function (): void {
    $calculator = app(PrescriptionScheduleCalculator::class);
    $startTime = now()->setTime(8, 0, 0);

    $detail = (object) [
        'frequency' => 'bid',
        'duration_days' => 2,
        'course_started_at' => $startTime->toDateTimeString(),
        'total_administrations' => 4,
        'schedule_type' => 'custom_times',
        'custom_dose_times' => ['10:00', '22:00'],
        'prn' => false,
    ];

    $slots = $calculator->buildDoseSchedule($detail);

    expect($slots)->toHaveCount(4)
        ->and($slots[0]->dueAt->format('H:i'))->toBe('10:00')
        ->and($slots[1]->dueAt->format('H:i'))->toBe('22:00')
        ->and($slots[2]->dueAt->format('H:i'))->toBe('10:00')
        ->and($slots[3]->dueAt->format('H:i'))->toBe('22:00');
});

