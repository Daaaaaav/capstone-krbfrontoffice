<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\AISecurityReports as ManagerAISecurityReports;

/**
 * IT Officer – Wazuh Security Reports
 *
 * Inherits ALL data-loading logic, refresh methods, and state from the Manager
 * component. The only difference is the layout wrapper (it-officer sidebar).
 *
 * Authorization is enforced at the route level via the 'is.it.officer' middleware
 * (see routes/web.php). No additional permission logic is needed here.
 *
 * The shared Blade view (livewire.pages.manager.a-i-security-reports) renders
 * identically for both roles; the layout attribute below substitutes the
 * correct sidebar/shell.
 */
#[Layout('layouts.it-officer')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends ManagerAISecurityReports
{
    // No overrides needed.
    // loadAlerts(), refreshAlerts(), pollRefresh(), toggleAutoRefresh(),
    // setSeverity(), toggleDetail(), and render() are all inherited.
}
