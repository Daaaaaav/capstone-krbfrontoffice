<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\AISecurityReports as ManagerAISecurityReports;

/**
 * IT Officer – Wazuh Security Reports
 *
 * Inherits ALL data-loading logic, refresh methods, and state from the Manager
 * component. The only differences are the layout wrapper (it-officer sidebar)
 * and the explicit render() override that follows the same pattern used by every
 * other IT Officer subclass in this project (see LSTMPredictions, OccupancyForecasting).
 *
 * Without the render() override, the inherited Manager render() returns a plain
 * view() object without ->layout() chained on it. While Livewire v3's SupportPageComponents
 * interceptor reads the #[Layout] attribute from the actual class and applies it, the
 * explicit override is the established project pattern and makes the layout contract clear.
 *
 * Authorization is enforced at the route level via the 'is.it.officer' middleware
 * (see routes/web.php). No additional permission logic is needed here.
 *
 * The shared Blade view (livewire.pages.manager.a-i-security-reports) renders
 * identically for both roles.
 */
#[Layout('layouts.it-officer')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends ManagerAISecurityReports
{
    public function render()
    {
        return parent::render()->layout('layouts.it-officer');
    }
}
