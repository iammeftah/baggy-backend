<?php

namespace App\Services;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class PasswordResetService
{
    /**
     * Generate and send OTP to user's email
     */
    public function sendOtp(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Delete any existing tokens for this email
        PasswordResetToken::where('email', $email)->delete();

        // Create new token (expires in 15 minutes)
        PasswordResetToken::create([
            'email' => $email,
            'token' => $otp,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // Send email
        Mail::to($email)->send(new ResetPasswordOtpMail($otp, $user->first_name));

        return [
            'message' => 'Code OTP envoyé avec succès',
            'email' => $email,
        ];
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $email, string $otp): bool
    {
        $resetToken = PasswordResetToken::where('email', $email)
            ->where('token', $otp)
            ->first();

        if (!$resetToken) {
            return false;
        }

        if ($resetToken->isExpired()) {
            $resetToken->delete();
            return false;
        }

        return true;
    }

    /**
     * Reset password with OTP verification
     */
    public function resetPassword(string $email, string $otp, string $newPassword): bool
    {
        // Verify OTP first
        if (!$this->verifyOtp($email, $otp)) {
            return false;
        }

        // Update user password
        $user = User::where('email', $email)->firstOrFail();
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Delete the used token
        PasswordResetToken::where('email', $email)->delete();

        return true;
    }

    /**
     * Resend OTP
     */
    public function resendOtp(string $email): array
    {
        // Check if user exists
        $user = User::where('email', $email)->firstOrFail();

        // Check for rate limiting (optional)
        $lastToken = PasswordResetToken::where('email', $email)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->first();

        if ($lastToken) {
            throw new \Exception('Veuillez attendre avant de demander un nouveau code');
        }

        return $this->sendOtp($email);
    }
}
