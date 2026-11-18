<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BillingPlan extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['active' => 'boolean'];
    protected $hidden = ['currentVersion','workingVersion','current_version','working_version'];
    public function versions() { return $this->hasMany(BillingPlanVersion::class)->orderByDesc('number'); }
    public function currentVersion() { return $this->hasOne(BillingPlanVersion::class)->ofMany(['number'=>'max'],fn($q)=>$q->where('status','published')); }
    public function workingVersion() { return $this->hasOne(BillingPlanVersion::class)->ofMany(['number'=>'max'],fn($q)=>$q->where('status','draft')); }
}
