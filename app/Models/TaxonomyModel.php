<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $video_count
 */
class TaxonomyModel extends Model
{
    use HasUuids;

    protected $table = 'taxonomies';

    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',
        'video_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'video_count' => 'integer',
    ];

    public $timestamps = false;
}
