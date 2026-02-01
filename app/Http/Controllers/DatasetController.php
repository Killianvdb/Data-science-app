<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dataset;

class DatasetController extends Controller
{
    public function index() {
        return view('datasets.index', [
            'datasets' => Dataset::all()
        ]);
    }

    public function create() {
        return view('datasets.create');
    }

    public function store(Request $request) {

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls,xml',
                'max:10240', // 10MB
            ],
        ]);

        $file = $request->file('file');

        // safety , clean name
        $filename = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file->getClientOriginalName());

        $path = $file->storeAs('datasets', $filename, 'private');



        return redirect()
            ->route('datasets.index')
            ->with('status', 'dataset-uploaded');
        }

    public function show(Dataset $dataset) {
        return view('datasets.show', compact('dataset'));
    }
}
