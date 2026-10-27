<?php

namespace App\Providers\Filament;

use App\Auth\SafeAppAuthentication;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Widgets\OperationalRequestsOverview;
use App\Http\Middleware\RestrictAdminPanelByIp;
use App\Http\Middleware\SetSecurityHeaders;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('Cible Real Estate')
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/brand/favicon.png'))
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                MenuItem::make()
                    ->label('الملف الشخصي')
                    ->url(fn (): string => filament()->getProfileUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigationItems([
                NavigationItem::make('الملف الشخصي')
                    ->label('الملف الشخصي')
                    ->url(fn (): string => filament()->getProfileUrl())
                    ->icon('heroicon-o-user-circle')
                    ->group('إدارة الشركة')
                    ->sort(3)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.auth.profile')),
            ])
            ->multiFactorAuthentication(
                providers: [SafeAppAuthentication::make()->recoverable()],
                isRequired: false,
            )
            ->colors([
                'primary' => [
                    50 => '#fbf8f1',
                    100 => '#f5eedc',
                    200 => '#ebddbb',
                    300 => '#dfc694',
                    400 => '#d3b173',
                    500 => '#c4a369',
                    600 => '#ab884d',
                    700 => '#896939',
                    800 => '#6b512e',
                    900 => '#523e25',
                    950 => '#312312',
                ],
                'danger' => [
                    50 => '#fdf3f3',
                    100 => '#fbe4e4',
                    200 => '#f8cece',
                    300 => '#f2abab',
                    400 => '#e77a7a',
                    500 => '#aa2121',
                    600 => '#941a1a',
                    700 => '#7b1515',
                    800 => '#661515',
                    900 => '#561616',
                    950 => '#300808',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                OperationalRequestsOverview::class,
            ])
            ->middleware([
                SetSecurityHeaders::class,
                RestrictAdminPanelByIp::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
