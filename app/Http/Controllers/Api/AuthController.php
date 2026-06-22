<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAAttr;

/**
 * @OA\Tag(name="Auth", description="Authentication endpoints")
 */

class AuthController extends Controller
{
    #[OAAttr\Post(
        path: '/api/register',
        tags: ['Auth'],
        summary: 'Register a new user',
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OAAttr\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                    new OAAttr\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                    new OAAttr\Property(property: 'password', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OAAttr\Response(response: 200, description: 'User created and token returned'),
            new OAAttr\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Auth"},
     *     summary="Register a new user",
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","email","password"}, @OA\Property(property="name", type="string", example="Jane Doe"), @OA\Property(property="email", type="string", format="email", example="jane@example.com"), @OA\Property(property="password", type="string", example="password123"))),
     *     @OA\Response(response=200, description="User created and token returned"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user', 'token'));
    }

    #[OAAttr\Post(
        path: '/api/login',
        tags: ['Auth'],
        summary: 'Login and receive a JWT token',
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OAAttr\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                    new OAAttr\Property(property: 'password', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OAAttr\Response(response: 200, description: 'JWT token returned'),
            new OAAttr\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Login and receive a JWT token",
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"email","password"}, @OA\Property(property="email", type="string", format="email", example="jane@example.com"), @OA\Property(property="password", type="string", example="password123"))),
     *     @OA\Response(response=200, description="JWT token returned"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'token' => $token
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     tags={"Auth"},
     *     summary="Get the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Authenticated user"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Invalidate the current token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out']);
    }
}
