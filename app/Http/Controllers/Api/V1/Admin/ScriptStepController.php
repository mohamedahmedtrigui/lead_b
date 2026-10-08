<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Scripts\DefaultScript;
use App\Domain\Scripts\ScriptService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateScriptStepRequest;
use App\Http\Resources\ScriptStepResource;
use App\Models\ScriptStep;
use Illuminate\Http\Request;

class ScriptStepController extends Controller
{
    public function __construct(private readonly ScriptService $scripts) {}

    public function index(): array
    {
        $steps = ScriptStep::query()->with('editor')->orderBy('position')->get();

        return [
            'data' => ScriptStepResource::collection($steps),
            // Defaults let the editor show which fields exist and restore wording.
            'defaults' => collect(DefaultScript::steps())->keyBy('key'),
        ];
    }

    public function update(UpdateScriptStepRequest $request, ScriptStep $scriptStep): ScriptStepResource
    {
        return new ScriptStepResource($this->scripts->update($scriptStep, $request->validated(), $request->user())->load('editor'));
    }

    public function reset(Request $request, ScriptStep $scriptStep): ScriptStepResource
    {
        return new ScriptStepResource($this->scripts->reset($scriptStep, $request->user())->load('editor'));
    }
}
