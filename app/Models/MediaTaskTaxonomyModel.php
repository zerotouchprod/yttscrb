<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $media_task_id
 * @property string $taxonomy_id
 */
class MediaTaskTaxonomyModel extends Model
{
    protected $table = 'media_task_taxonomies';

    protected $fillable = [
        'media_task_id',
        'taxonomy_id',
    ];

    /** @var bool */
    public $timestamps = false;

    /** Incrementing is off — composite PK with UUIDs */
    public $incrementing = false;

    /** @var string */
    protected $primaryKey = null; // Composite PK: media_task_id + taxonomy_id

    /** @var string */
    protected $keyType = 'string';
}
