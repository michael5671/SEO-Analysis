<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Heading extends Model {
    protected $fillable = ['run_id','url','url_hash','level','text','text_hash','is_focus'];
    public function run(){ return $this->belongsTo(Run::class); }
}
