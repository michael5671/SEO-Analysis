<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapPaaHeading extends Model {
    protected $table = 'map_paa_headings';
    protected $fillable = ['run_id','paa_item_id','heading_id','similarity'];

    public function run() { return $this->belongsTo(Run::class); }
    public function paa() { return $this->belongsTo(PaaItem::class, 'paa_item_id'); }
    public function heading(){ return $this->belongsTo(Heading::class, 'heading_id'); }
}
