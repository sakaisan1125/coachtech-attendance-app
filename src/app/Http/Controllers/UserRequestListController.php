<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CorrectionRequest;

class UserRequestListController extends Controller
{
    public function pending(Request $request)
    {
        $user = $request->user();
        $rows = CorrectionRequest::with(['attendance'])
            ->where('requested_by', $user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        return view('attendance.request', [
            'activeTab' => 'pending',
            'rows' => $rows,
        ]);
    }

    public function approved(Request $request)
    {
        $user = $request->user();
        $rows = CorrectionRequest::with(['attendance'])
            ->where('requested_by', $user->id)
            ->where('status', 'approved')
            ->latest('approved_at')
            ->get();

        return view('attendance.request', [
            'activeTab' => 'approved',
            'rows' => $rows,
        ]);
    }
}
