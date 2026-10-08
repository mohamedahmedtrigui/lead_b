<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        $phone = '+216'.fake()->unique()->numerify('########');

        return [
            'dedupe_key' => 'phone:'.$phone,
            'source_created_at' => now()->subDays(rand(0, 10)),
            'name' => fake()->name(),
            'source' => 'Paid',
            'form' => 'Leads For parents',
            'channel' => 'Phone',
            'stage' => 'Intake',
            'source_owner' => 'Unassigned',
            'phone' => $phone,
        ];
    }

    public function assignedTo(int $userId): static
    {
        return $this->afterMaking(function (Lead $lead) use ($userId) {
            $lead->assigned_to = $userId;
            $lead->assigned_at = now();
        });
    }
}
