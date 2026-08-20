<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\AISecurityReports as ManagerAISecurityReports;

/**
 * IT Officer – Wazuh Security Reports
 *
 * Inherits ALL data-loading logic, refresh methods, state, and the
 * #[Computed] filteredAlerts property from the Manager component.
 * The only differences are the layout wrapper (it-officer sidebar)
 * and the render() override that applies it — matching the established
 * pattern used by every other IT Officer subclass in this project
 * (see LSTMPredictions, OccupancyForecasting).
 *
 * The shared Blade view (livewire.pages.manager.a-i-security-reports)
 * accesses filteredAlerts via $this->filteredAlerts (the #[Computed]
 * property), so it renders identically for both roles.
 *
 * Authorization is enforced at the route level via the 'is.it.officer'
 * middleware (see routes/web.php). No additional permission logic is
 * needed here.
 */
#[Layout('layouts.it-officer')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends ManagerAISecurityReports
{
    public function render()
    {
        // parent::render() returns a plain view() – apply the IT Officer
        // layout via the Livewire v3 View::macro('layout', ...) exactly
        // as LSTMPredictions and OccupancyForecasting do.
        return parent::render()->layout('layouts.it-officer');
    }
}
