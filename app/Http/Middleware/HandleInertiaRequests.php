<?php
namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\Currency;

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
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Skip database queries during installation
        if ($request->is('install/*') || $request->is('update/*') || !file_exists(storage_path('installed'))) {
            // Get available languages even during installation
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }

            $globalSettings = [
                'currencySymbol' => '$',
                'currencyNname' => 'US Dollar',
                'base_url' => config('app.url'),
                'image_url' => config('app.url'),
                'is_demo' => config('app.is_demo', false),
                'is_saas' => isSaas(),
                'availableLanguages' => $availableLanguages,
            ];
        } else {
            // Get system settings
            $settings = settings();
            // Get currency symbol
            $currencyCode = $settings['defaultCurrency'] ?? 'USD';
            $currency = Currency::where('code', $currencyCode)->first();
            $currencySettings = [];
            if ($currency) {
                $currencySettings = [
                    'currencySymbol' => $currency->symbol,
                    'currencyNname' => $currency->name
                ];
            } else {
                $currencySettings = [
                    'currencySymbol' => '$',
                    'currencyNname' => 'US Dollar'
                ];
            }

            // Get available languages
            $languagesFile = resource_path('lang/language.json');
            $availableLanguages = [];
            if (file_exists($languagesFile)) {
                $availableLanguages = json_decode(file_get_contents($languagesFile), true) ?? [];
            }

            // Merge currency settings with other settings
            $globalSettings = array_merge($settings, $currencySettings);
            $globalSettings['base_url'] = config('app.url');
            $globalSettings['image_url'] = config('app.url');
            $globalSettings['is_demo'] = config('app.is_demo');
            $globalSettings['is_saas'] = isSaas();
            $globalSettings['availableLanguages'] = $availableLanguages;
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'base_url' => config('app.url'),
            'image_url' => config('app.url'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user(),
                'roles' => fn() => $request->user()?->roles->pluck('name'),
                'permissions' => function() use ($request) {
                    $user = $request->user();
                    if (!$user) return [];
                    if (in_array($user->type ?? '', ['superadmin', 'super admin', 'company'])) {
                        return \App\Models\Permission::pluck('name');
                    }
                    $names = $user->getAllPermissions()->pluck('name')->toArray();
                    // Ensure activity logs permission for HR roles (menu + access)
                    if (in_array($user->type ?? '', ['admin', 'manager', 'staff'], true)
                        && ! in_array('view-activity-logs', $names, true)) {
                        $names[] = 'view-activity-logs';
                    }
                    return $names;
                },
                'active_branch_id' => session('active_branch_id'),
                'branches' => fn() => $request->user()?->branches()->where('status', 'active')->select('branches.id', 'branches.name')->get(),
                'must_create_branch' => fn() => $request->user() && $request->user()->type === 'company' && $request->user()->branches()->where('status', 'active')->count() === 0,
            ],
            'userLanguage' => $request->user()?->lang ?? 'en',
            'isImpersonating' => session('impersonated_by') ? true : false,
            'ziggy' => fn(): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'globalSettings' => $globalSettings,
            'is_demo' => env('IS_DEMO', false)
        ];
    }
}