<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model diagnosa keluhan / gejala kerusakan otomotif (PRD Phase 2 - Feature 2).
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $symptom_title
 * @property string|null $symptom_description
 * @property array<string>|null $symptom_keywords
 * @property string $suspected_root_cause
 * @property string $severity
 * @property array<string> $recommended_part_types
 * @property array<float>|null $embedding
 */
class DiagnosticSymptom extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'symptom_title',
        'symptom_description',
        'symptom_keywords',
        'suspected_root_cause',
        'severity',
        'recommended_part_types',
        'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symptom_keywords' => 'array',
            'recommended_part_types' => 'array',
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
