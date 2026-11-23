<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_duty_time_fields_are_persisted(): void
    {
        $employee = Employee::factory()->create([
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'duty_start_time' => '09:00:00',
        ]);
    }
}
