<?php

namespace App\Providers;

use App\Enums\AccessPortal;
use App\Filament\Staff\Resources\Budgets\ClientBudgetsSection;
use App\Models\AccessLog;
use App\Models\AccountHistory;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\Service;
use App\Models\User;
use App\Policies\AccessLogPolicy;
use App\Policies\AccountPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ClientPortalPolicy;
use App\Policies\PieceApprovalPortalPolicy;
use App\Policies\PiecePolicy;
use App\Policies\ServicePolicy;
use App\Support\Pdf\DompdfRenderer;
use App\Support\Pdf\PdfRenderer;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, DompdfRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The emailed link opens the password screen of the account's own portal (plan D-11).
        ResetPassword::createUrlUsing(
            fn (User $user, string $token): string => Filament::getPanel(AccessPortal::forAccount($user)->value)
                ->getResetPasswordUrl($token, $user),
        );

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Piece::class, PiecePolicy::class);

        // The client card lists the client's budgets now that the module exists (spec 003, RF-46).
        ClientBudgetsSection::register();

        // One policy per model, so the portal abilities are registered by name (spec 003).
        Gate::define('portal.view', [ClientPortalPolicy::class, 'view']);
        Gate::define('portal.updateAddress', [ClientPortalPolicy::class, 'updateAddress']);
        Gate::define('portal.updateContacts', [ClientPortalPolicy::class, 'updateContacts']);

        // The client's answer on a piece is also a named ability, limited to its own client (spec 004).
        Gate::define('portal.pieces.approve', [PieceApprovalPortalPolicy::class, 'approve']);
        Gate::define('portal.pieces.reject', [PieceApprovalPortalPolicy::class, 'reject']);

        Gate::policy(User::class, AccountPolicy::class);
        Gate::policy(AccessLog::class, AccessLogPolicy::class);
        Gate::policy(AccountHistory::class, AccessLogPolicy::class);
    }
}
