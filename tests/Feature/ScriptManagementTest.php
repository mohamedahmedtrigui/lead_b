<?php

namespace Tests\Feature;

use App\Domain\Scripts\ScriptService;
use App\Models\ScriptStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScriptService::class)->syncDefaults();
    }

    public function test_dispatcher_reads_but_cannot_edit_the_script(): void
    {
        $dispatcher = $this->dispatcher();
        $step = ScriptStep::where('key', 'introduction')->sole();

        $this->actingAs($dispatcher)->getJson('/api/v1/script')->assertOk()->assertJsonCount(14);
        $this->actingAs($dispatcher)->putJson("/api/v1/admin/script-steps/{$step->id}", ['title' => 'Hack'])->assertForbidden();
    }

    public function test_admin_edits_wording_and_option_labels(): void
    {
        $step = ScriptStep::where('key', 'shared')->sole();

        $this->actingAs($this->admin())->putJson("/api/v1/admin/script-steps/{$step->id}", [
            'title' => 'Covoiturage organisé',
            'script' => 'Nouveau discours',
            'question' => 'Seriez-vous ouvert au partage ?',
            'prompts' => ['shared_direction' => 'Dans quel sens ?'],
            'options' => ['shared_transport' => ['YES' => 'Oui volontiers']],
        ])->assertOk()->assertJsonPath('title', 'Covoiturage organisé');

        $step->refresh();
        $this->assertSame('Dans quel sens ?', $step->prompts['shared_direction']);
        $labels = collect($step->options['shared_transport'])->pluck('label', 'value');
        $this->assertSame('Oui volontiers', $labels['YES']);
        $this->assertSame('Peut-être', $labels['MAYBE']);
    }

    public function test_admin_cannot_invent_option_values(): void
    {
        $step = ScriptStep::where('key', 'shared')->sole();

        $this->actingAs($this->admin())->putJson("/api/v1/admin/script-steps/{$step->id}", [
            'title' => 'X',
            'options' => ['shared_transport' => ['ALWAYS' => 'Toujours']],
        ])->assertUnprocessable();
    }
}
