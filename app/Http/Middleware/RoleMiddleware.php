<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $roleId)
    {

        if ($request->user()->role_id != $roleId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // A seller signs in before the admin approves them (the app shows the
        // waiting screen), so the token alone must not open the seller API.
        if ((int) $roleId === 2 && $request->user()->business_status !== 'active') {
            return response()->json([
                'status'  => false,
                'message' => 'Your business account is awaiting approval.',
            ], 403);
        }

        return $next($request);
    }
}
