<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeadImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "\xEF\xBB\xBFCreated,Name,Email,Source,Form,Channel,Stage,Owner,Labels,Phone,Secondary phone number,WhatsApp number\n"
        ."10/08/2026 3:19am,Mjjedi Hedi,,Paid,Leads For parents,Phone,Intake,Unassigned,,+21623173698,,\n"
        ."10/07/2026 5:22am,Bell La,,Paid,,Messenger,Intake,Unassigned,,,,\n"
        ."10/07/2026 4:27am,Bell La,,Paid,Leads For parents,Phone,Intake,Unassigned,,+21654339889,,\n"
        ."10/06/2026 9:50pm,Bochra Boukadida Bk,,Paid,Leads For parents,Phone,Intake,Unassigned,,56351445,,\n"
        .",,,,,,,,,,,\n";

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('leads.csv', self::CSV);
    }

    public function test_import_normalizes_phones_and_merges_duplicates(): void
    {
        $this->actingAs($this->admin())
            ->post('/api/v1/admin/leads/imports', ['file' => $this->upload()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('import.created_count', 3);

        $this->assertSame(3, Lead::count());
        $this->assertDatabaseHas('leads', ['name' => 'Bochra Boukadida Bk', 'phone' => '+21656351445']);
        // Messenger row merged into the phone lead without overwriting its channel.
        $this->assertDatabaseHas('leads', ['name' => 'Bell La', 'phone' => '+21654339889', 'channel' => 'Phone']);

        $hedi = Lead::where('name', 'Mjjedi Hedi')->sole();
        $this->assertSame('2026-10-08 02:19', $hedi->source_created_at->utc()->format('Y-m-d H:i'));
    }

    public function test_reimport_never_overwrites_pipeline_or_qualification_data(): void
    {
        $admin = $this->admin();
        $dispatcher = $this->dispatcher();

        $this->actingAs($admin)->post('/api/v1/admin/leads/imports', ['file' => $this->upload()], ['Accept' => 'application/json']);

        $lead = Lead::where('phone', '+21623173698')->sole();
        $lead->forceFill(['assigned_to' => $dispatcher->id, 'status' => LeadStatus::QUALIFIED, 'nrp_attempts' => 2])->save();
        $lead->qualification()->create(['departure' => 'Sfax']);

        $this->actingAs($admin)->post('/api/v1/admin/leads/imports', ['file' => $this->upload()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('import.created_count', 0);

        $lead->refresh();
        $this->assertSame(3, Lead::count());
        $this->assertSame(LeadStatus::QUALIFIED, $lead->status);
        $this->assertSame($dispatcher->id, $lead->assigned_to);
        $this->assertSame(2, $lead->nrp_attempts);
        $this->assertSame('Sfax', $lead->qualification()->first()->departure);
    }

    public function test_import_and_distribute_in_one_go(): void
    {
        $admin = $this->admin();
        $this->dispatcher();
        $this->dispatcher();

        $this->actingAs($admin)
            ->post('/api/v1/admin/leads/imports', ['file' => $this->upload(), 'distribute' => 1], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(0, Lead::whereNull('assigned_to')->count());
    }
}
