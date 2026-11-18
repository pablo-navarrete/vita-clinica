<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerDirectoryProfile extends Model
{
    protected $guarded=['id'];
    protected $casts=['publication_enabled'=>'boolean','show_public_contact'=>'boolean','show_public_social_links'=>'boolean','authorized_at'=>'datetime','withdrawn_at'=>'datetime','suspended_at'=>'datetime'];
    public function customer(){return $this->belongsTo(Customer::class);}
    public function publishedReviews(){return $this->hasMany(DirectoryReview::class, 'customer_id', 'customer_id')->published();}
    public function logo(){return $this->belongsTo(Logo::class);}
    public function banner(){return $this->belongsTo(WebsiteBanner::class,'website_banner_id');}
}
