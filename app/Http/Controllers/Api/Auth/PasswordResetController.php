<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    protected PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Send OTP to user's email
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->passwordResetService->sendOtp($request->email);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'email' => $result['email'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi du code',
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $isValid = $this->passwordResetService->verifyOtp(
            $request->email,
            $request->otp
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code OTP vérifié avec succès',
        ]);
    }

    /**
     * Reset password with OTP
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $success = $this->passwordResetService->resetPassword(
            $request->email,
            $request->otp,
            $request->password
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès',
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->passwordResetService->resendOtp($request->email);

            return response()->json([
                'success' => true,
                'message' => 'Code OTP renvoyé avec succès',
                'data' => [
                    'email' => $result['email'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        }
    }
}
