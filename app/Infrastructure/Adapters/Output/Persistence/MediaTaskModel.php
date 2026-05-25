<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $youtube_url
 * @property string|null $video_id
 * @property string|null $title
 * @property string|null $slug
 * @property string $status
 * @property string|null $workflow_id
 * @property string|null $result_text
 * @property array<string, mixed>|null $summary
 * @property int|null $duration_sec
 * @property string|null $error_message
 * @property int|null $user_id
 * @property string|null $user_identifier
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $dmca_removed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 */
class MediaTaskModel extends Model
{
    protected $table = 'media_tasks';

    /**
     * Taxonomy tags attached to this media task (pivot: media_task_taxonomies).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function taxonomies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\TaxonomyModel::class,
            'media_task_taxonomies',
            'media_task_id',
            'taxonomy_id',
        );
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'youtube_url',
        'video_id',
        'title',
        'slug',
        'status',
        'workflow_id',
        'result_text',
        'summary',
        'duration_sec',
        'error_message',
        'user_id',
        'user_identifier',
        'completed_at',
        'failed_at',
        'dmca_removed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'duration_sec' => 'integer',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'dmca_removed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
