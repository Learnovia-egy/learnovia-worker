<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Hash;

class VerifyWorkerPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $password = $request->header('X-Worker-Password');

        if (!$password) {
            return response()->json(['message' => 'Unauthorized: Missing worker password'], 401);
        }

        $credential = WorkerCredential::first();

        if (!$credential || !Hash::check($password, $credential->password_hash)) {
            return response()->json(['message' => 'Unauthorized: Invalid worker password'], 401);
        }

        return $next($request);
    }
}
