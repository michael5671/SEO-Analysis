<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Run;
use App\Services\PaaWordCloudBuilder;
class WordCloudController extends Controller
{
    //
    public function show($id)
    {
        $run = Run::findOrFail($id);
        return view('wordcloud', ['run' => $run]);
    }

    public function data($id, PaaWordCloudBuilder $builder)
    {
        $run = Run::findOrFail($id);
        $data = $builder->forRun($run->id, topK:100);
        return response()->json($data);
    }
}
