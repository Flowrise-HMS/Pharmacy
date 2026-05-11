<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Pharmacy\Database\Factories\DrugFactory;

class Drug extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'source_provider',
        'source_identifier',
        'rxnorm_code',
        'ndc_code',
        'generic_name',
        'display_name',
        'brand_name',
        'strength_text',
        'dosage_form_text',
        'synonyms',
        'raw_payload',
        'search_rank',
        'times_prescribed',
        'times_stocked',
        'is_cached_external',
        'is_active',
    ];

    protected $casts = [
        'synonyms' => 'array',
        'raw_payload' => 'array',
        'search_rank' => 'integer',
        'times_prescribed' => 'integer',
        'times_stocked' => 'integer',
        'is_cached_external' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): DrugFactory
    {
        return DrugFactory::new();
    }
}
