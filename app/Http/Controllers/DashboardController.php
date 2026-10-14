<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyContext = app(CompanyContext::class);
        $activeCompany = $companyContext->getCompany();
        $activeCompanyId = $activeCompany ? (string) $activeCompany->_id : null;

        $stats = [
            'has_active_company' => ! empty($activeCompany),
            'company' => $activeCompany ? [
                'id' => (string) $activeCompany->_id,
                'name' => $activeCompany->name,
                'slug' => $activeCompany->slug,
                'modules' => $activeCompany->modules ?? [],
            ] : null,
            'whatsapp' => [
                'enabled' => $activeCompany ? $activeCompany->isModuleEnabled('whatsapp') : false,
                'total_instances' => 0,
                'connected_instances' => 0,
                'total_messages' => 0,
                'instances_list' => [],
            ],
            'appointments' => [
                'enabled' => $activeCompany ? $activeCompany->isModuleEnabled('appointments') : false,
                'total' => 0,
                'today' => 0,
                'upcoming' => [],
            ],
            'inventory' => [
                'enabled' => $activeCompany ? $activeCompany->isModuleEnabled('inventory') : false,
                'total_products' => 0,
                'low_stock_count' => 0,
                'low_stock_items' => [],
            ],
            'services' => [
                'enabled' => $activeCompany ? $activeCompany->isModuleEnabled('services') : false,
                'total_services' => 0,
                'featured_services' => [],
            ],
            'team' => [
                'total_members' => 0,
            ],
            'saas' => [
                'can_manage' => false,
                'total_companies' => 0,
                'total_users' => 0,
            ],
        ];

        if ($activeCompanyId) {
            // WhatsApp CRM
            if ($stats['whatsapp']['enabled']) {
                $instances = WhatsappInstance::where('company_id', $activeCompanyId)->get();
                $stats['whatsapp']['total_instances'] = $instances->count();
                $stats['whatsapp']['connected_instances'] = $instances->where('status', 'connected')->count();
                $stats['whatsapp']['total_messages'] = WhatsappMessage::where('company_id', $activeCompanyId)->count();
                $stats['whatsapp']['instances_list'] = $instances->take(4)->map(fn ($inst) => [
                    'id' => (string) $inst->_id,
                    'instance_name' => $inst->instance_name,
                    'status' => $inst->status ?? 'disconnected',
                    'business_name' => $inst->business_name ?? null,
                ])->values()->toArray();
            }

            // Agenda & Citas
            if ($stats['appointments']['enabled']) {
                $now = now();
                $startOfDay = now()->startOfDay();
                $endOfDay = now()->endOfDay();

                $stats['appointments']['total'] = WhatsappAppointment::where('company_id', $activeCompanyId)->count();
                $stats['appointments']['today'] = WhatsappAppointment::where('company_id', $activeCompanyId)
                    ->where('starts_at', '>=', $startOfDay)
                    ->where('starts_at', '<=', $endOfDay)
                    ->count();

                $stats['appointments']['upcoming'] = WhatsappAppointment::where('company_id', $activeCompanyId)
                    ->where('starts_at', '>=', $now)
                    ->orderBy('starts_at', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(fn ($app) => [
                        'id' => (string) $app->_id,
                        'summary' => $app->summary ?? 'Cita Programada',
                        'user_phone' => $app->user_phone ?? 'Sin teléfono',
                        'starts_at' => $app->starts_at ? $app->starts_at->toIso8601String() : null,
                        'sync_status' => $app->sync_status ?? 'pending',
                    ])
                    ->values()
                    ->toArray();
            }

            // Inventario
            if ($stats['inventory']['enabled']) {
                $products = Product::where('company_id', $activeCompanyId)->get();
                $stats['inventory']['total_products'] = $products->count();

                $lowStock = $products->filter(fn ($p) => ($p->stock ?? 0) <= ($p->min_stock ?? 0));
                $stats['inventory']['low_stock_count'] = $lowStock->count();
                $stats['inventory']['low_stock_items'] = $lowStock
                    ->take(5)
                    ->map(fn ($p) => [
                        'id' => (string) $p->_id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'stock' => $p->stock ?? 0,
                        'min_stock' => $p->min_stock ?? 0,
                        'price' => $p->price ?? 0,
                    ])
                    ->values()
                    ->toArray();
            }

            // Servicios
            if ($stats['services']['enabled']) {
                $services = Service::where('company_id', $activeCompanyId)->where('is_active', true)->get();
                $stats['services']['total_services'] = $services->count();
                $stats['services']['featured_services'] = $services->take(4)->map(fn ($s) => [
                    'id' => (string) $s->_id,
                    'name' => $s->name,
                    'price' => $s->price ?? 0,
                    'duration_minutes' => $s->duration_minutes ?? 0,
                ])->values()->toArray();
            }

            // Equipo
            $stats['team']['total_members'] = CompanyUser::where('company_id', $activeCompanyId)->count();
        }

        // Permisos de administración global SaaS
        if ($user) {
            $permissionCache = app(PermissionCacheService::class);
            $userPermissions = $permissionCache->getUserPermissions($user, $activeCompanyId);

            if (in_array('administration', $userPermissions) || in_array('companies', $userPermissions)) {
                $stats['saas']['can_manage'] = true;
                $stats['saas']['total_companies'] = Company::count();
                $stats['saas']['total_users'] = User::count();
            }
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
