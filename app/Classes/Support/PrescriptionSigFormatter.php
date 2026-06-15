<?php

namespace Modules\Pharmacy\Classes\Support;

use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Models\PrescriptionDetail;

class PrescriptionSigFormatter
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function rows(PrescriptionDetail $detail): array
    {
        $rows = [];

        if ($dose = $this->doseText($detail)) {
            $rows[] = ['label' => __('Dose / strength'), 'value' => $dose];
        }

        if ($route = $this->routeLabel($detail)) {
            $rows[] = ['label' => __('Route'), 'value' => $route];
        }

        if ($frequency = $this->frequencyLabel($detail)) {
            $rows[] = ['label' => __('Frequency'), 'value' => $frequency];
        }

        if ($detail->duration_days) {
            $rows[] = [
                'label' => __('Duration'),
                'value' => __(':days day(s)', ['days' => $detail->duration_days]),
            ];
        }

        if ($detail->course_started_at || $detail->course_end_at) {
            $course = collect([
                $detail->course_started_at?->format('Y-m-d'),
                $detail->course_end_at?->format('Y-m-d'),
            ])->filter()->implode(' → ');

            if ($course !== '') {
                $rows[] = ['label' => __('Course dates'), 'value' => $course];
            }
        }

        if ($detail->refills !== null && $detail->refills > 0) {
            $rows[] = ['label' => __('Refills'), 'value' => (string) $detail->refills];
        }

        if ($detail->prn) {
            $prn = __('As needed (PRN)');
            if ($detail->indication) {
                $prn .= ' — '.$detail->indication;
            }
            $rows[] = ['label' => __('PRN'), 'value' => $prn];
        }

        if (filled($detail->instructions)) {
            $rows[] = ['label' => __('Special instructions'), 'value' => $detail->instructions];
        }

        return $rows;
    }

    public function sigLine(PrescriptionDetail $detail): string
    {
        $segments = [];

        if ($dose = $this->doseText($detail)) {
            $segments[] = __('Take :dose', ['dose' => $dose]);
        }

        if ($route = $this->routeLabel($detail)) {
            $segments[] = $route;
        }

        if ($frequency = $this->frequencyLabel($detail)) {
            $segments[] = $frequency;
        }

        if ($detail->duration_days) {
            $segments[] = __('for :days day(s)', ['days' => $detail->duration_days]);
        }

        if ($detail->prn && ! $this->frequencyLabel($detail)) {
            $segments[] = __('as needed');
            if ($detail->indication) {
                $segments[] = __('for :indication', ['indication' => $detail->indication]);
            }
        }

        if (filled($detail->instructions)) {
            $segments[] = $detail->instructions;
        }

        return $segments !== [] ? implode(', ', $segments) : '—';
    }

    public function summary(PrescriptionDetail $detail): string
    {
        $parts = array_filter([
            $this->doseText($detail),
            $this->frequencyLabel($detail),
            $this->routeLabel($detail),
            $detail->duration_days ? $detail->duration_days.' '.__('day(s)') : null,
            filled($detail->instructions) ? $detail->instructions : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    protected function doseText(PrescriptionDetail $detail): ?string
    {
        if (filled($detail->dosage)) {
            return trim((string) $detail->dosage);
        }

        if ($detail->dose_amount !== null) {
            $unit = $detail->doseUnit?->label ?? $detail->doseUnit?->name;

            return trim($detail->dose_amount.' '.($unit ?? ''));
        }

        return null;
    }

    protected function frequencyLabel(PrescriptionDetail $detail): ?string
    {
        if (! filled($detail->frequency)) {
            return null;
        }

        return MedicationFrequency::tryFrom((string) $detail->frequency)?->getLabel()
            ?? strtoupper((string) $detail->frequency);
    }

    protected function routeLabel(PrescriptionDetail $detail): ?string
    {
        if (! filled($detail->route)) {
            return null;
        }

        return MedicationRoute::tryFrom((string) $detail->route)?->getLabel()
            ?? strtoupper((string) $detail->route);
    }
}
