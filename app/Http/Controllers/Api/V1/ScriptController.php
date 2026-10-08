<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Scripts\ScriptService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScriptStepResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only call script used by the qualification wizard.
 */
class ScriptController extends Controller
{
    public function index(ScriptService $scripts): AnonymousResourceCollection
    {
        return ScriptStepResource::collection($scripts->steps());
    }
}
