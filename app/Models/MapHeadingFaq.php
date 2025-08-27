<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapHeadingFaq extends Model {
    protected $table = 'map_heading_faqs';
    protected $fillable = ['run_id','heading_id','faq_item_id','similarity'];

    public function run(){return $this->belongsTo(Run::class);}
    public function heading(){return $this->belongsTo(Heading::class,'heading_id');}
    public function faq(){return $this->belongsTo(FaqItem::class,'faq_item_id');}
}
