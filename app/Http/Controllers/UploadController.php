<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function create()
    {
        return view('upload.create');
    }

    // Guardar archivo y crear registro en DB
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,xml|max:10240', // 10 MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('datasets');

        $dataset = Dataset::create([
            'user_id' => Auth::id(),
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'status' => 'uploaded',
        ]);

        return redirect()->route('dashboard')->with('success', 'File uploaded successfully!');
    }
}
