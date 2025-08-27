<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    //
    protected $fillable = ['query', 'mode'];

    public function headings()
    {
        return $this->hasMany(Heading::class);
    }

    public function faqItems()
    {
        return $this->hasMany(FaqItem::class);
    }

    public function paaItems()
    {
        return $this->hasMany(PaaItem::class);
    }
    public function mapPaaHeadings(){ return $this->hasMany(MapPaaHeading::class); }
    public function mapHeadingFaqs(){ return $this->hasMany(MapHeadingFaq::class); }
}

