<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);
        abort_unless($request->user()->is_admin, 403);

        session()->put('impersonator_id', $request->user()->id);
        auth()->login($user);

        return redirect()->route('home');
    }

    public function stop(): RedirectResponse
    {
        $originalUser = User::findOrFail(session()->pull('impersonator_id'));

        auth()->login($originalUser);

        return redirect()->route('home');
    }
}
