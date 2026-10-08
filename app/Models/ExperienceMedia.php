<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceMedia extends Model
{
    protected $table = 'experience_media';

    protected $guarded = ['id'];

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }
}
