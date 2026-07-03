<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiSettingsController extends Controller
{
    /** Troca o provider global de geração de imagem (somente admin da IA). */
    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(AiProvider::values())],
        ]);

        Setting::set(AiProvider::SETTING_KEY, $data['provider']);

        $label = collect(AiProvider::options())->firstWhere('value', $data['provider'])['label'];

        return back()->with('status', "IA da aplicação alterada para {$label}.");
    }
}
