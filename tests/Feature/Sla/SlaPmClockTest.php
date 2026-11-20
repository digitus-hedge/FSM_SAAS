<?php

namespace Tests\Feature\Sla;

use App\Models\SlaPolicy;
use Tests\Feature\Maintenance\MaintenanceTestCase;

/** Blueprint 7.1: a request made by a maintenance schedule waits for approval like any other, unless it is an Emergency. */
class SlaPmClockTest extends MaintenanceTestCase
{
    public function test_a_maintenance_request_has_no_clock_until_it_is_approved_but_an_emergency_one_has(): void
    {
        foreach (['medium', 'emergency'] as $priority) {
            SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'service_category_id' => null, 'priority' => $priority, 'response_minutes' => 60, 'resolution_minutes' => 240]);
        }

        $routine   = $this->schedule(['name' => 'Routine', 'priority' => 'medium']);
        $emergency = $this->schedule(['name' => 'Urgent', 'priority' => 'emergency']);
        $this->runJob();

        $a = $this->pmRequests($routine)->first();
        $b = $this->pmRequests($emergency)->first();

        $this->assertSame('pending', $a->status);
        $this->assertNull($a->sla_clock_started_at);
        $this->assertNull($a->sla_resolution_due_at);
        $this->assertNotNull($b->sla_clock_started_at);
        $this->assertNotNull($b->sla_resolution_due_at);
    }
}
