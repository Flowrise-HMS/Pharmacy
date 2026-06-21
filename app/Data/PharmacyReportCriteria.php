<?php

namespace Modules\Pharmacy\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final readonly class PharmacyReportCriteria
{
    public function __construct(
        public CarbonInterface $startDate,
        public CarbonInterface $endDate,
        public ?string $branchId = null,
        public string $lineKind = 'all',
        public string $channel = 'all',
    ) {}

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function resolvePreset(?string $preset, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return match ($preset) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'today', null, '' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromRequest(array $query, ?CarbonInterface $now = null): self
    {
        $now ??= now();
        $preset = isset($query['preset']) ? (string) $query['preset'] : 'today';

        if ($preset === 'custom' || (isset($query['start_date']) && isset($query['end_date']))) {
            $start = Carbon::parse((string) ($query['start_date'] ?? $now->toDateString()))->startOfDay();
            $end = Carbon::parse((string) ($query['end_date'] ?? $now->toDateString()))->endOfDay();
        } else {
            [$start, $end] = self::resolvePreset($preset, $now);
        }

        $branchId = $query['branch_id'] ?? null;
        $lineKind = isset($query['line_kind']) ? (string) $query['line_kind'] : 'all';
        $channel = isset($query['channel']) ? (string) $query['channel'] : 'all';

        if (! in_array($lineKind, ['all', 'medication', 'service'], true)) {
            $lineKind = 'all';
        }

        if (! in_array($channel, ['all', 'pos', 'clinical'], true)) {
            $channel = 'all';
        }

        return new self(
            startDate: $start,
            endDate: $end,
            branchId: is_string($branchId) && $branchId !== '' ? $branchId : null,
            lineKind: $lineKind,
            channel: $channel,
        );
    }
}
