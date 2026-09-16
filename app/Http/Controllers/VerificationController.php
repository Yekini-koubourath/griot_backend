<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(
                config('app.frontend_url', 'http://localhost:3000')
                . '/auth/email-verifie'
            );
        }

        $request->fulfill();

        return redirect(
            config('app.frontend_url', 'http://localhost:3000')
            . '/auth/email-verifie'
        );
    }
}