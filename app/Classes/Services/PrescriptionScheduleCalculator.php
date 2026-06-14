<?php

namespace Modules\Pharmacy\Classes\Services;

use Carbon\Carbon;
use Modules\Pharmacy\Classes\Data\DoseSlot;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\PrescriptionDetail;

class PrescriptionScheduleCalculator
{
    /**
     * @param  array{frequency?:string,duration_days?:int,prn?:bool,course_started_at?:Carbon|string|null,max_administrations?:int|null}  $input
     * @return array{total_administrations:?int,course_end_at:Carbon,course_started_at:Carbon,schedule_summary:string}
     */
    public function compute(array $input): array
    {
        $frequency = MedicationFrequency::tryFrom($input['frequency'] ?? '');
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
            $statDuration = (int) config('clinical.mar_schedule.stat_duration_days', 1);

            return [
                'total_administrations' => 1,
                'course_started_at' => $courseStartedAt,
                'course_end_at' => $courseStartedAt->copy()->addDays($statDuration),
                'schedule_summary' => ($frequency?->getLabel() ?? 'Once').' × 1 dose',
            ];
        }

        $timesPerDay = $frequency?->timesPerDay() ?? 1;
        $totalAdministrations = $timesPerDay * $durationDays;
        $freqLabel = $frequency?->getLabel() ?? ($input['frequency'] ?? 'Unknown');

        return [
            'total_administrations' => $totalAdministrations,
            'course_started_at' => $courseStartedAt,
            'course_end_at' => $courseStartedAt->copy()->addDays($durationDays),
            'schedule_summary' => "{$freqLabel} × {$durationDays} day(s) = {$totalAdministrations} doses",
        ];
    }

    /**
     * @return list<DoseSlot>
     */
    public function buildDoseSchedule(PrescriptionDetail $detail): array
    {
        $frequency = MedicationFrequency::tryFrom($detail->frequency ?? '');
        $courseStartedAt = Carbon::parse($detail->course_started_at ?? now());
        $durationDays = max(1, (int) ($detail->duration_days ?? 1));

        if ($detail->prn || $frequency === MedicationFrequency::PRN) {
            return [];
        }

        if (in_array($frequency, [MedicationFrequency::STAT, MedicationFrequency::ONCE], true)) {
            return [new DoseSlot(1, $courseStartedAt->copy())];
        }

        if ($frequency?->isIntervalBased()) {
            $intervalHours = $frequency->hoursInterval();
            $total = $detail->total_administrations ?? ($frequency->timesPerDay() * $durationDays);
            $slots = [];

            for ($i = 0; $i < $total; $i++) {
                $slots[] = new DoseSlot(
                    $i + 1,
                    $courseStartedAt->copy()->addHours($intervalHours * $i)
                );
            }

            return $slots;
        }

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

        if ($detail->total_administrations) {
            $slots = array_slice($slots, 0, $detail->total_administrations);
            foreach ($slots as $index => $slot) {
                $slots[$index] = new DoseSlot($index + 1, $slot->dueAt);
            }
        }

        return $slots;
    }

    /**
     * @return list<string>
     */
    protected function defaultTimesForFrequency(?MedicationFrequency $frequency): array
    {
        $configured = config('clinical.mar_default_times', []);

        return match ($frequency) {
            MedicationFrequency::QD => $configured['qd'] ?? ['08:00'],
            MedicationFrequency::BID => $configured['bid'] ?? ['08:00', '20:00'],
            MedicationFrequency::TID => $configured['tid'] ?? ['08:00', '14:00', '20:00'],
            MedicationFrequency::QID => $configured['qid'] ?? ['06:00', '12:00', '18:00', '22:00'],
            default => ['08:00'],
        };
    }
}
