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

        $user->assignRole('student');

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
     * Permite al usuario logueado ingresar su DNI y actualizar su fullname,
     * solo si el campo DNI todavía está vacío.
     */
    public function updateDniAndFullname(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // 1. Verificar si el usuario ya tiene un DNI establecido
        if (!empty($user->dni)) {
            return response()->json([
                'status' => false,
                'message' => 'Ya tiene un DNI registrado. No se permite la actualización.'
            ], 403); // Forbidden
        }

        // 2. Validar el DNI de entrada (debe ser único globalmente, ya que no tiene uno)
        $validator = Validator::make($request->all(), [
            'dni' => 'required|string|digits:8|unique:users,dni',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $dni = $request->input('dni');

        // 3. Obtener el nombre completo desde la API externa
        $fullname = $this->getFullNameFromDNI($dni);

        if (!$fullname) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo validar el DNI proporcionado o no es válido.'
            ], 400); // Bad Request
        }

        try {
            // 4. Actualizar el usuario
            $user->dni = $dni;
            $user->fullname = $fullname;
            $user->save();

            // 5. Devolver la respuesta de éxito
            return response()->json([
                'status' => true,
                'message' => 'DNI y nombre completo actualizados exitosamente.',
                'user' => $user->only(['id', 'name', 'email', 'dni', 'fullname']) // Devuelve solo campos relevantes
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Error interno al guardar la información.',
                'error' => $th->getMessage()
            ], 500);
        }
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
                $fullname = trim($data['apellido_paterno'] . ' ' . $data['apellido_materno'] . ' ' . $data['nombres']);

                return $fullname;
            }

            return null;

        } catch (\Exception $e) {
            // Log::error('Error al conectar con apiperu.dev: ' . $e->getMessage());
            return null;
        }
    }
}