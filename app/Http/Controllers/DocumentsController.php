<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentsController extends Controller
{
    public function index(){
        return Inertia::render('Documents');
    }
}
