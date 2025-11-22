<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();

            // 1. Buscar usuario por email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // --- LOGIN (Usuario existe) ---
                // Actualizamos avatar si ha cambiado, o nombre si quieres mantenerlo sincronizado
                $user->update([
                    'avatar' => $user->avatar ?? $googleUser->getAvatar(),
                    'name' => $googleUser->getName(),
                ]);
            } else {
                // --- REGISTRO (Usuario nuevo) ---
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)),
                    'fullname' => null,
                    'dni' => null,
                    'phone' => null,
                ]);
                $user->assignRole('student');
            }

            // 2. Crear Token Sanctum
            $token = $user->createToken('auth_token_google')->plainTextToken;

            // 3. Preparar datos para el Frontend
            $frontendData = [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->getRoleNames()->first()
                ]
            ];

            // 4. Crear la Cookie (Válida por 24 horas = 1440 minutos)
            // Importante: path '/' para que el frontend la pueda leer
            setcookie(
                name: 'auth_data',                              // Nombre
                value: json_encode($frontendData),       // Valor
                expires_or_options: time() + (60 * 60 * 24),    // Expiración (Unix timestamp)
                path: '/',                                      // Path
                domain: "",                                     // Domain (vacío = actual)
                secure: app()->isProduction(),                  // Secure (HTTPS en prod)
                httponly: false                                 // HttpOnly
            );

            // 5. Redirigir al Frontend con la cookie pegada
            // Usamos la variable de entorno o un fallback
            $frontendUrl = config('app.frontend_url');

            return redirect($frontendUrl . '/academico/dashboard');

        } catch (Exception $e) {
            Log::error('Error en callback de Google: ' . $e->getMessage());

            // Si falla, redirigir al login del frontend con un error
            $frontendUrl = config('app.frontend_url');
            return redirect($frontendUrl . '/login?error=auth_failed');
        }
    }
}