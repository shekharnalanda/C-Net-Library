<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

            if ($setting->key === 'map_embed_url' && filled($value)) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $allowed = $host === 'google.com'
                    || $host === 'www.google.com'
                    || $host === 'maps.google.com'
                    || str_ends_with($host, '.google.com');

                if (! $allowed) {
                    throw ValidationException::withMessages([
                        "settings.$id" => 'Google Maps embed URL must use a google.com host.',
                    ]);
                }
            }

            if ($setting->type === 'json') {
                $values = is_array($value)
                    ? $value
                    : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
                $newValue = json_encode(array_values(array_filter($values ?? [], fn ($item) => $item !== '')));
            } else {
                $newValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }

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
