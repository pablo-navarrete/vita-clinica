<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalSignatureAsset extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['uuid', 'customer_id', 'user_id', 'kind', 'path', 'mime', 'bytes', 'width', 'height', 'sha256'];

    protected $hidden = ['path'];

    protected static function booted(): void
    {
        // Replacements create another asset. Existing versions are never edited or deleted here.
        static::updating(fn () => throw new \LogicException('Signature assets are immutable.'));
        static::deleting(fn () => throw new \LogicException('Signature assets must be retained.'));
    }
}
