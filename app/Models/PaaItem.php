<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaaItem extends Model {
    protected $fillable = ['run_id','question','question_hash','intent','freq', 'embedding'];
    public function run(){ return $this->belongsTo(Run::class); }
}
