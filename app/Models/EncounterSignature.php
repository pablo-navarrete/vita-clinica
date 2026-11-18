<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncounterSignature extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['identity'];
    protected $casts = ['identity' => 'encrypted:array', 'accepted' => 'boolean', 'signed_at' => 'datetime', 'authenticated_at' => 'datetime'];

    public function documentVersion() { return $this->belongsTo(EncounterDocumentVersion::class, 'document_version_id'); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Signature evidence is immutable.'));
        static::deleting(fn () => throw new \LogicException('Signature evidence must be retained.'));
    }
}
