<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\VerifyEmailController as AuthEmailVerificationRequest;
use Backpack\CRUD\app\Library\Auth\UserFromCookie;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

class VerifyEmailController extends AuthEmailVerificationRequest
{
}
