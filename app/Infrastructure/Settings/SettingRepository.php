<?php

namespace App\Infrastructure\Settings;

class SettingRepository
{
    /**
     * @return array<string, string>
     */
    public function valuesForGroup(string $group): array
    {
        return Setting::query()
            ->where('setting_group', $group)
            ->get(['setting_key', 'setting_value'])
            ->mapWithKeys(static fn (Setting $setting): array => [$setting->setting_key => $setting->setting_value])
            ->all();
    }
}
