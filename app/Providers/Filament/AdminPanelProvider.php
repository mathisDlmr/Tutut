<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BilanMensuelComptabilite;
use App\Filament\Pages\SendEmail;
use App\Filament\Pages\TutorManageUvs;
use App\Filament\Resources\Admin\ComptabiliteResource;
use App\Filament\Resources\Admin\SalleResource;
use App\Filament\Resources\Admin\SemaineResource as AdminSemaineResource;
use App\Filament\Resources\Admin\SemestreResource as AdminSemestreResource;
use App\Filament\Resources\Admin\TuteursEmployesResource as AdminTuteursEmployesResource;
use App\Filament\Resources\Tutee\InscriptionCreneauResource as TuteeInscriptionCreneauResource;
use App\Filament\Resources\Tutor\ComptabiliteTutorResource;
use App\Filament\Resources\Tutor\CreneauResource as TutorCreneauResource;
use App\Filament\Resources\Tutor\FeedbackResource as TutorFeedbackResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminWidget;
use App\Filament\Widgets\TuteeCreneauxWidget;
use App\Filament\Widgets\TutorCreneauxTableWidget;
use App\Models\Semestre;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['fr', 'en'])
                ->displayLocale('fr')
                ->labels([
                    'fr' => 'Français',
                    'en' => 'English',
                ])
                ->renderHook('panels::topbar.end');
        });

        return $panel
            ->default()
            ->id('tutut')
            ->path('/tutut')
            ->brandName("Tut'ut - ".Semestre::where('is_active', true)->first()->code)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
                AdminTuteursEmployesResource::class,
                AdminSemestreResource::class,
                AdminSemaineResource::class,
                ComptabiliteResource::class,
                SalleResource::class,
                TutorCreneauResource::class,
                ComptabiliteTutorResource::class,
                TutorFeedbackResource::class,
                TuteeInscriptionCreneauResource::class,
            ])
            ->pages([
                Dashboard::class,
                SendEmail::class,
                TutorManageUvs::class,
            ])   
            ->widgets([
                TutorCreneauxTableWidget::class,
                TuteeCreneauxWidget::class,
                AdminWidget::class,
            ])         
            ->middleware([
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
