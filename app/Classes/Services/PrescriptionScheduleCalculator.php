<?php

namespace Modules\Pharmacy\Classes\Services;

use Carbon\Carbon;
use Modules\Clinical\Contracts\PrescriptionScheduleCalculatorContract;
use Modules\Pharmacy\Classes\Data\DoseSlot;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\PrescriptionDetail;

class PrescriptionScheduleCalculator implements PrescriptionScheduleCalculatorContract
{
    /**
     * @param  array{frequency?:MedicationFrequency|string|null,duration_days?:int,prn?:bool,course_started_at?:Carbon|string|null,max_administrations?:int|null}  $input
     * @return array{total_administrations:?int,course_end_at:Carbon,course_started_at:Carbon,schedule_summary:string}
     */
    public function compute(array $input): array
    {
        $frequency = $this->normalizeFrequency($input['frequency'] ?? null);
        $durationDays = max(1, (int) ($input['duration_days'] ?? 1));
        $isPrn = filter_var($input['prn'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $courseStartedAt = isset($input['course_started_at'])
            ? Carbon::parse($input['course_started_at'])
            : now();

        if ($isPrn || $frequency === MedicationFrequency::PRN) {
            $maxAdministrations = isset($input['max_administrations'])
                ? (int) $input['max_administrations']
                : null;

            return [
                'total_administrations' => $maxAdministrations,
                'course_started_at' => $courseStartedAt,
                'course_end_at' => $courseStartedAt->copy()->addDays($durationDays),
                'schedule_summary' => "PRN × {$durationDays} day(s)",
            ];
        }

        if (in_array($frequency, [MedicationFrequency::STAT, MedicationFrequency::ONCE], true)) {
            $statDuration = (int) app_settings()->clinicalValue('mar_stat_duration_days', config('clinical.mar_schedule.stat_duration_days', 1));

            return [
                'total_administrations' => 1,
                'course_started_at' => $courseStartedAt,
                'course_end_at' => $courseStartedAt->copy()->addDays($statDuration),
                'schedule_summary' => ($frequency?->getLabel() ?? 'Once').' × 1 dose',
            ];
        }

        $timesPerDay = $frequency?->timesPerDay() ?? 1;
        $totalAdministrations = $timesPerDay * $durationDays;
        $freqLabel = $frequency?->getLabel()
            ?? (is_string($input['frequency'] ?? null) ? (string) $input['frequency'] : 'Unknown');

        $intervalHours = $frequency?->intervalHours();
        $courseEndAt = $intervalHours !== null
            ? $courseStartedAt->copy()->addHours($intervalHours * $totalAdministrations)
            : $courseStartedAt->copy()->addDays($durationDays);

        return [
            'total_administrations' => $totalAdministrations,
            'course_started_at' => $courseStartedAt,
            'course_end_at' => $courseEndAt,
            'schedule_summary' => "{$freqLabel} × {$durationDays} day(s) = {$totalAdministrations} doses",
        ];
    }

    /**
     * @return list<DoseSlot>
     */
    public function buildDoseSchedule(object $detail): array
    {
        $frequency = $this->normalizeFrequency($detail->frequency ?? null);
        $courseStartedAt = Carbon::parse($detail->course_started_at ?? now());
        $durationDays = max(1, (int) ($detail->duration_days ?? 1));
        $isPrn = filter_var($detail->prn ?? false, FILTER_VALIDATE_BOOLEAN) || $frequency === MedicationFrequency::PRN;

        if ($isPrn) {
            return [];
        }

        if (in_array($frequency, [MedicationFrequency::STAT, MedicationFrequency::ONCE], true)) {
            return [new DoseSlot(1, $courseStartedAt->copy())];
        }

        $scheduleType = $detail->schedule_type ?? null;
        $customDoseTimes = is_array($detail->custom_dose_times ?? null)
            ? $detail->custom_dose_times
            : (is_string($detail->custom_dose_times ?? null) ? json_decode($detail->custom_dose_times, true) : null);

        // Custom daily times strategy
        if ($scheduleType === 'custom_times' && ! empty($customDoseTimes)) {
            $times = array_values(array_filter(array_map(function ($time): string {
                return is_array($time) ? (string) ($time['time'] ?? '') : (string) $time;
            }, (array) $customDoseTimes)));
            sort($times);
            $slots = [];
            $sequence = 1;

            for ($day = 0; $day < $durationDays; $day++) {
                foreach ($times as $time) {
                    if (! str_contains($time, ':')) {
                        continue;
                    }

                    [$hour, $minute] = array_map('intval', explode(':', $time));
                    $dueAt = $courseStartedAt->copy()->startOfDay()->addDays($day)->setTime($hour, $minute);

                    if ($day === 0 && $dueAt->lt($courseStartedAt)) {
                        $dueAt = $courseStartedAt->copy();
                    }

                    $slots[] = new DoseSlot($sequence++, $dueAt);
                }
            }

            if (! empty($detail->total_administrations)) {
                $slots = array_slice($slots, 0, (int) $detail->total_administrations);
                foreach ($slots as $index => $slot) {
                    $slots[$index] = new DoseSlot($index + 1, $slot->dueAt);
                }
            }

            return $slots;
        }

        // Automated interval strategy (explicit interval_based customization)
        if ($scheduleType === 'interval_based') {
            $intervalHours = $frequency?->intervalHours() ?? $frequency?->hoursInterval();

            if ($intervalHours !== null) {
                $total = ! empty($detail->total_administrations)
                    ? (int) $detail->total_administrations
                    : (($frequency?->timesPerDay() ?? 1) * $durationDays);
                $slots = [];

                for ($i = 0; $i < $total; $i++) {
                    $slots[] = new DoseSlot(
                        $i + 1,
                        $courseStartedAt->copy()->addHours($intervalHours * $i)
                    );
                }

                return $slots;
            }
        }

        // Standard ward times strategy (or default for non-interval frequencies without custom schedule)
        if ($scheduleType === 'standard_times' || ($scheduleType === null && ! ($frequency?->isIntervalBased() ?? false))) {
            $defaultTimes = $this->defaultTimesForFrequency($frequency);
            $slots = [];
            $sequence = 1;

            for ($day = 0; $day < $durationDays; $day++) {
                foreach ($defaultTimes as $time) {
                    [$hour, $minute] = array_map('intval', explode(':', $time));
                    $dueAt = $courseStartedAt->copy()->startOfDay()->addDays($day)->setTime($hour, $minute);

                    if ($day === 0 && $dueAt->lt($courseStartedAt)) {
                        $dueAt = $courseStartedAt->copy();
                    }

                    $slots[] = new DoseSlot($sequence++, $dueAt);
                }
            }

            if (! empty($detail->total_administrations)) {
                $slots = array_slice($slots, 0, (int) $detail->total_administrations);
                foreach ($slots as $index => $slot) {
                    $slots[$index] = new DoseSlot($index + 1, $slot->dueAt);
                }
            }

            return $slots;
        }

        // Interval-based frequency fallback (Q4H, Q6H, Q8H, Q12H)
        $intervalHours = $frequency?->intervalHours() ?? $frequency?->hoursInterval();

        if ($intervalHours !== null) {
            $total = ! empty($detail->total_administrations)
                ? (int) $detail->total_administrations
                : (($frequency?->timesPerDay() ?? 1) * $durationDays);
            $slots = [];

            for ($i = 0; $i < $total; $i++) {
                $slots[] = new DoseSlot(
                    $i + 1,
                    $courseStartedAt->copy()->addHours($intervalHours * $i)
                );
            }

            return $slots;
        }

        // Fallback to standard times if no interval can be determined
        $defaultTimes = $this->defaultTimesForFrequency($frequency);
        $slots = [];
        $sequence = 1;

        for ($day = 0; $day < $durationDays; $day++) {
            foreach ($defaultTimes as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $dueAt = $courseStartedAt->copy()->startOfDay()->addDays($day)->setTime($hour, $minute);

                if ($day === 0 && $dueAt->lt($courseStartedAt)) {
                    $dueAt = $courseStartedAt->copy();
                }

                $slots[] = new DoseSlot($sequence++, $dueAt);
            }
        }

        if (! empty($detail->total_administrations)) {
            $slots = array_slice($slots, 0, (int) $detail->total_administrations);
            foreach ($slots as $index => $slot) {
                $slots[$index] = new DoseSlot($index + 1, $slot->dueAt);
            }
        }

        return $slots;
    }

    protected function normalizeFrequency(MedicationFrequency|string|null $frequency): ?MedicationFrequency
    {
        if ($frequency instanceof MedicationFrequency) {
            return $frequency;
        }

        if (is_string($frequency)) {
            $normalized = strtolower(trim($frequency));
            $aliased = match ($normalized) {
                'od' => 'qd',
                'bd' => 'bid',
                'tds' => 'tid',
                'qds' => 'qid',
                default => $normalized,
            };

            return enum_try_from(MedicationFrequency::class, $aliased);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function defaultTimesForFrequency(?MedicationFrequency $frequency): array
    {
        $configured = app_settings()->clinicalValue('mar_default_times', config('clinical.mar_default_times', []));

        return match ($frequency) {
            MedicationFrequency::QD => $configured['qd'] ?? ['08:00'],
            MedicationFrequency::BID => $configured['bid'] ?? ['08:00', '20:00'],
            MedicationFrequency::TID => $configured['tid'] ?? ['08:00', '14:00', '20:00'],
            MedicationFrequency::QID => $configured['qid'] ?? ['06:00', '12:00', '18:00', '22:00'],
            default => ['08:00'],
        };
    }
}
