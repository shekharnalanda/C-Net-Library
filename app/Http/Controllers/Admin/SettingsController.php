<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request, AuditService $audit)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ]);

        foreach ($validated['settings'] as $id => $value) {
            $setting = Setting::findOrFail($id);
            $old = ['value' => $setting->value];
            $newValue = $setting->type === 'json' && is_array($value)
                ? json_encode(array_values(array_filter($value)))
                : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

            if ($newValue === $setting->value) {
                continue;
            }

            $setting->update(['value' => $newValue]);

            $audit->log(
                action: 'setting.updated',
                auditable: $setting,
                oldValues: $old,
                newValues: ['value' => $newValue],
                request: $request,
            );
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
