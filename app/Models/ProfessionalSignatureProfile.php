<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalSignatureProfile extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'method', 'signature_asset_id', 'stamp_asset_id'];

    public function signatureAsset()
    {
        return $this->belongsTo(ProfessionalSignatureAsset::class, 'signature_asset_id');
    }

    public function stampAsset()
    {
        return $this->belongsTo(ProfessionalSignatureAsset::class, 'stamp_asset_id');
    }
}
