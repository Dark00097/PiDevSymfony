<?php

namespace App\Service;

final class AdminSecuritySettingsService
{
    private const STORAGE_PATH = __DIR__.'/../../var/data/admin_security_settings.json';

    public function getSettings(): array
    {
        $defaults = $this->getDefaultSettings();

        if (!is_file(self::STORAGE_PATH)) {
            $this->saveSettings($defaults);

            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents(self::STORAGE_PATH), true);
        if (!is_array($decoded)) {
            $this->saveSettings($defaults);

            return $defaults;
        }

        return array_merge($defaults, [
            'require_biometric_on_admin_login' => (bool) ($decoded['require_biometric_on_admin_login'] ?? false),
            'require_biometric_on_sensitive_actions' => (bool) ($decoded['require_biometric_on_sensitive_actions'] ?? false),
            'enable_email_otp' => (bool) ($decoded['enable_email_otp'] ?? false),
            'updated_at' => (string) ($decoded['updated_at'] ?? $defaults['updated_at']),
        ]);
    }

    public function saveSettings(array $input): array
    {
        $settings = [
            'require_biometric_on_admin_login' => (bool) ($input['require_biometric_on_admin_login'] ?? false),
            'require_biometric_on_sensitive_actions' => (bool) ($input['require_biometric_on_sensitive_actions'] ?? false),
            'enable_email_otp' => (bool) ($input['enable_email_otp'] ?? false),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $directory = dirname(self::STORAGE_PATH);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(self::STORAGE_PATH, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $settings;
    }

    public function getStoragePath(): string
    {
        return realpath(dirname(self::STORAGE_PATH)) !== false
            ? realpath(dirname(self::STORAGE_PATH)).DIRECTORY_SEPARATOR.basename(self::STORAGE_PATH)
            : self::STORAGE_PATH;
    }

    private function getDefaultSettings(): array
    {
        return [
            'require_biometric_on_admin_login' => false,
            'require_biometric_on_sensitive_actions' => false,
            'enable_email_otp' => true,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}
