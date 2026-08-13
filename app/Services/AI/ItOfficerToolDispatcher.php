<?php

namespace App\Services\AI;

use App\Services\AI\Tools\UserManagementTool;
use App\Services\AI\Tools\RoomManagementTool;
use App\Services\AI\Tools\VehicleManagementTool;
use App\Services\AI\Tools\StorageManagementTool;
use Illuminate\Support\Facades\Log;

/**
 * ItOfficerToolDispatcher
 *
 * Extends the base ToolDispatcher by adding the four IT Officer management
 * tools: UserManagementTool, RoomManagementTool, VehicleManagementTool,
 * StorageManagementTool.
 *
 * The base analytics/availability tools are inherited so the IT Officer
 * chatbot can answer analytics queries exactly as the Manager/Receptionist
 * chatbots do, without any code duplication.
 *
 * This class is used ONLY by ItOfficerChatModal. The existing ToolDispatcher
 * and ChatModal are untouched.
 */
class ItOfficerToolDispatcher extends ToolDispatcher
{
    public function __construct()
    {
        // Register base analytics/availability tools (RoomAvailability, VehicleAvailability,
        // Analytics, Guestbook, Delivery, Forecast, Occupancy, Announcement)
        parent::__construct();

        // Register IT Officer management tools
        $this->register(app(UserManagementTool::class));
        $this->register(app(RoomManagementTool::class));
        $this->register(app(VehicleManagementTool::class));
        $this->register(app(StorageManagementTool::class));

        if (config('app.debug')) {
            Log::info('ItOfficerToolDispatcher: initialized', [
                'tools' => array_keys($this->all()),
            ]);
        }
    }
}
