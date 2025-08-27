<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerpSnapshot extends Model
{
    //
    protected $fillable = [
        'run_id',
        'query_used',
        'url',
        'url_hash',
        'position',
        'has_faq_rich'
    ];
    public function run()
    {
        return $this->belongsTo(Run::class);
    }
}
