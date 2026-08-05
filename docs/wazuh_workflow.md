# Wazuh System Integration Workflow

This document outlines the step-by-step workflow of how the Wazuh security system integrates with our application.

| **Step** | **Who is doing the work?** | **What are they doing?** |
| :--- | :--- | :--- |
| **1. Log Reading** | **Wazuh Agent** *(not Laravel)* | Continuously reads raw system logs (e.g., `/var/log/auth.log`) on the server being monitored. |
| **2. Analysis** | **Wazuh Manager** *(not Laravel)* | Parses those log lines, matches them against security rules, and generates a structured JSON alert. |
| **3. Push** | **Wazuh Integrator** *(not Laravel)* | Sends an HTTP `POST` request with the JSON payload to your Laravel API endpoint. |
| **4. Database Save** | **Laravel API Endpoint** | Receives the POST request and saves the fields directly into your MySQL `wazuh_alerts` table. |
| **5. Display** | **Laravel Livewire Dashboard** | Performs standard MySQL `SELECT` queries to display the alerts in the UI. |
