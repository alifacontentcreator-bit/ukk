<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {

        // Belum login
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Role user tidak sesuai
        if (!in_array($request->user()->role, $roles)) {

            abort(
                403,
                'Anda tidak memiliki hak akses ke halaman ini.'
            );
        }

        return $next($request);
    }
}