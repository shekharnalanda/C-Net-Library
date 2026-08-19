<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ]);

        foreach ($validated['settings'] as $id => $value) {
            $setting = Setting::findOrFail($id);
            $setting->update([
                'value' => $setting->type === 'json' && is_array($value)
                    ? json_encode(array_values(array_filter($value)))
                    : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
            ]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
