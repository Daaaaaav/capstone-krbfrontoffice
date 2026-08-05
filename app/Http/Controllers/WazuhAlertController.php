<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WazuhAlertController extends Controller
{
    /**
     * Store a newly created alert in storage from Wazuh Webhook.
     */
    public function store(Request $request)
    {
        // 1. Validate Token
        $validToken = config('services.wazuh.token');
        if ($validToken && $request->header('X-Wazuh-Token') !== $validToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Extract Data
        $data = $request->json()->all();

        $rule = $data['rule'] ?? [];
        $agent = $data['agent'] ?? [];

        // Provide safe fallbacks
        $ruleId = $rule['id'] ?? null;
        $ruleLevel = isset($rule['level']) ? (int) $rule['level'] : 0;
        $description = $rule['description'] ?? 'Wazuh Alert';
        $agentName = $agent['name'] ?? null;
        $fullLog = $data['full_log'] ?? json_encode($data);

        // 3. Insert Record
        $alert = \App\Models\WazuhAlert::create([
            'rule_id' => $ruleId,
            'rule_level' => $ruleLevel,
            'description' => $description,
            'agent_name' => $agentName,
            'raw_log' => $fullLog,
        ]);

        return response()->json([
            'message' => 'Alert successfully ingested',
            'data' => $alert
        ], 201);
    }
}
