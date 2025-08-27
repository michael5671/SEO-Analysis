<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqItem extends Model {
    protected $fillable = ['run_id','url','url_hash','question','question_hash','answer','answer_hash'];
    public function run(){ return $this->belongsTo(Run::class); }
}
