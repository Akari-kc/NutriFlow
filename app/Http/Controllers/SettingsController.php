<?php

namespace App\Http\Controllers;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', [
            'dark' => session('dark_mode', false),
        ]);
    }

    public function updateTheme(\Illuminate\Http\Request $request)
    {
        $data = $request->validate(['dark_mode' => 'nullable|boolean']);
        session(['dark_mode' => (bool)($data['dark_mode'] ?? false)]);
        return back()->with('status','Theme updated');
    }

    public function updatePassword(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'current_password' => ['required','current_password'],
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user->password = $data['password']; // hashed by cast
        $user->save();
        return back()->with('status','Your password has been updated.');
    }
}
