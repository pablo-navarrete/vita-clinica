<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryReview extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['rating'=>'integer', 'publication_consented_at'=>'datetime', 'withdrawn_at'=>'datetime'];

    public function scopePublished($query) { return $query->whereNull('directory_reviews.withdrawn_at'); }
    public function professional() { return $this->belongsTo(User::class, 'professional_id'); }
    public function encounter() { return $this->belongsTo(Encounter::class); }
    public function replies() { return $this->hasMany(DirectoryReviewReply::class)->orderBy('id'); }
}
