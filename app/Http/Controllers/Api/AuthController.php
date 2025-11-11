<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado después de la autenticación'
            ], 404);
        }

        $token = $user->createToken('auth_token_' . $user->name)->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logout exitoso'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al cerrar la sesión',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Registra un nuevo usuario.
     */
    public function register(Request $request)
    {
        // 1. Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'dni' => 'required|string|digits:8|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Obtener el nombre completo desde la API externa
        $fullname = $this->getFullNameFromDNI($request->dni);

        if (!$fullname) {
            // Si la API no devuelve un nombre, lanzamos un error de validación para el DNI
            throw ValidationException::withMessages([
                'dni' => ['No se pudo validar el DNI o el DNI no es válido.'],
            ]);
        }

        // 3. Crear el usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // El 'cast' en el modelo User se encarga de hashear
            'dni' => $request->dni,
            'fullname' => $fullname,
        ]);

        // 4. Generar un token (usando Sanctum, ya que vi 'HasApiTokens' en tu modelo)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Devolver la respuesta
        return response()->json([
            'message' => 'Usuario registrado exitosamente.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    /**
     * Endpoint público para consultar un DNI y devolver el nombre completo.
     */
    public function getFullnameByDni(Request $request)
    {
        // 1. Validar el DNI
        $validator = Validator::make($request->all(), [
            'dni' => 'required|string|digits:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Obtener el nombre completo
        $fullname = $this->getFullNameFromDNI($request->dni);

        if ($fullname) {
            return response()->json([
                'success' => true,
                'dni' => $request->dni,
                'fullname' => $fullname,
            ]);
        }

        // 3. Devolver error si no se encuentra
        return response()->json([
            'success' => false,
            'message' => 'No se pudo encontrar información para el DNI proporcionado.',
        ], 404);
    }


    /**
     * Método privado para interactuar con la API de apiperu.dev
     *
     * @param string $dni
     * @return string|null
     */
    private function getFullNameFromDNI(string $dni): ?string
    {
        // Obtenemos el token desde el archivo de configuración (ver config/services.php)
        $token = config('services.apiperu.token');

        if (!$token) {
            // Log::error('APIPERU_TOKEN no está configurado.'); // Es buena idea loggear esto
            return null;
        }

        $url = "https://apiperu.dev/api/dni/{$dni}";

        try {
            $response = Http::withToken($token)->get($url);

            // Verificar si la respuesta fue exitosa y 'success' es true
            if ($response->successful() && $response->json('success') === true) {
                $data = $response->json('data');

                // Concatenamos los nombres y apellidos
                $fullname = trim($data['nombres'] . ' ' . $data['apellido_paterno'] . ' ' . $data['apellido_materno']);

                return $fullname;
            }

            return null;

        } catch (\Exception $e) {
            // Log::error('Error al conectar con apiperu.dev: ' . $e->getMessage());
            return null;
        }
    }
}