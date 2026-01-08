<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeRedirectController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->isManager() || $request->user()->isAdmin()) {
            return redirect()->route('manager.entries');
        }

        return redirect()->route('profile');
    }
}
