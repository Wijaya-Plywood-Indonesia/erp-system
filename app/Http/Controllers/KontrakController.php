<?php

namespace App\Http\Controllers;

use App\Models\KontrakKerja;
use Illuminate\Http\Request;

class KontrakController extends Controller
{
    //  
    public function bulkPrint(Request $request)
    {
        $ids = explode(',', $request->ids);

        $kontraks = KontrakKerja::whereIn('id', $ids)->get();

        return view('kontrak.bulk-print', compact('kontraks'));
    }
}
