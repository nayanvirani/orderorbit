<?php

namespace App\Http\Middleware;

use App\Support\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff access (G28): a staff member without a module sees "ask your store owner" instead of it. */
class StaffModules
{
    public function handle(Request $request, Closure $next): Response
    {
        $module = StaffAccess::moduleFor($request->route()?->getName());
        $user = $request->attributes->get('storeUser');
        $store = $request->attributes->get('store');
        if ($module === null || $user === null || StaffAccess::can($user, $module)) {
            return $next($request);
        }
        if ($request->expectsJson() && ! $request->headers->has(\App\Support\Spa\Page::HEADER)) {
            return response()->json(['message' => 'Ask your store owner for access to this part of Growvia.'], 403);
        }

        return page('private', ['module' => StaffAccess::MODULES[$module][0], 'owner' => $store ? StaffAccess::ownerName($store) : null], 403)->toResponse($request);
    }
}
