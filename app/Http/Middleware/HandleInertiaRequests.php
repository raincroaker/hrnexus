<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Handle an incoming request. Add no-cache headers for Inertia (XHR) responses
     * so that navigation (clicking links) always fetches fresh data instead of
     * serving a cached response. Without this, you may see "old" data until you
     * do a full page reload (F5).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $avatarUrl = null;

        // Fetch employee avatar if user exists
        if ($user) {
            $employee = Employee::where('email', $user->email)->first();
            if ($employee && $employee->avatar) {
                $avatarUrl = Storage::url($employee->avatar);
            }
        }

        // Merge avatar into user array if it exists
        $userArray = $user ? array_merge($user->toArray(), ['avatar' => $avatarUrl]) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $userArray,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrfToken' => csrf_token(), // Share CSRF token so it's always fresh
        ];
    }
}
