<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\PasswordUpdatedNotification;
use App\Support\Locale\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;

class UpdatedUserController extends Controller
{
    public function updateProfile(Request $request)
    {
        $lang = Locales::normalize($request->header('locale'));
        App::setLocale($lang);
        $request->user()->update($request->all());

        return response()->json(['message' => __('events.profile_updated')]);
    }

    public function updatePassword(Request $request)
    {
        $lang = Locales::normalize($request->header('locale'));
        App::setLocale($lang);
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        // Revoke all Sanctum tokens (including current) — forces logout on all devices
        $request->user()->tokens()->delete();

        $request->user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Use user's preferred language, fallback to request locale
        $userLang = Locales::normalize($request->user()->lang, $lang);
        Notification::send($request->user(), new PasswordUpdatedNotification($userLang));

        return response()->json(['message' => __('events.password_updated')]);
    }
}
