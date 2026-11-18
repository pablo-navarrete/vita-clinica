<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncounterDocumentVersion extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'attachment_manifest'];
    protected $casts = ['payload' => 'encrypted', 'attachment_manifest' => 'encrypted:array'];

    public function signature() { return $this->hasOne(EncounterSignature::class, 'document_version_id'); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Signed versions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Signed versions must be retained.'));
    }
}
