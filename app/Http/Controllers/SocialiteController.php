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
                    'fullname' => $googleUser->getName(),
                ]);
            } else {
                // --- REGISTRO (Usuario nuevo) ---
                $user = User::create([
                    'name'     => $googleUser->getName(),
                    'email'    => $googleUser->getEmail(),
                    'avatar'   => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)),
                    'fullname' => null,
                    'dni'      => null, 
                    'phone'    => null, 
                ]);
                $user->assignRole('student'); 
            }

            // 2. Crear Token Sanctum
            $token = $user->createToken('auth_token_google')->plainTextToken;

            // 3. Respuesta JSON para tu prueba en navegador
            return response()->json([
                'status' => '¡ÉXITO! Usuario autenticado con modelo actualizado.',
                'action' => $user->wasRecentlyCreated ? 'Registro Nuevo' : 'Login Existente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ],
                'token' => $token
            ]);

        } catch (Exception $e) {
            Log::error('Error en callback de Google: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'Error',
                'message' => 'No se pudo iniciar sesión con Google.',
                'debug_error' => $e->getMessage()
            ], 500);
        }
    }
}