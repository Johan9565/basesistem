<?php

namespace App\Support;

class LandingPalette
{
    /**
     * Default blue palette for the public landing page.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            '--landing-bg' => '#f3f7fc',
            '--landing-ink' => '#0c2440',
            '--landing-primary' => '#1a4b8c',
            '--landing-primary-deep' => '#123868',
            '--landing-primary-soft' => '#dbeafe',
            '--landing-accent' => '#93c5fd',
            '--landing-accent-strong' => '#2563eb',
            '--landing-muted' => '#5b738c',
            '--landing-cta' => '#60a5fa',
            '--landing-cta-text' => '#0c2440',
            '--landing-surface' => '#e8f1fb',
            '--landing-footer' => '#0a1f38',
            '--landing-hero-from' => '#0f3a6e',
            '--landing-border' => '#c5d8ef',
        ];
    }

    /**
     * Named presets available in the control panel.
     *
     * @return array<string, array{label: string, colors: array<string, string>}>
     */
    public static function presets(): array
    {
        return [
            'azul' => [
                'label' => 'Azul océano',
                'colors' => self::defaults(),
            ],
            'verde' => [
                'label' => 'Verde clínico',
                'colors' => [
                    '--landing-bg' => '#f9fbf8',
                    '--landing-ink' => '#19352f',
                    '--landing-primary' => '#315b50',
                    '--landing-primary-deep' => '#234b42',
                    '--landing-primary-soft' => '#eaf3ef',
                    '--landing-accent' => '#f6c990',
                    '--landing-accent-strong' => '#c47a42',
                    '--landing-muted' => '#60736d',
                    '--landing-cta' => '#f3b46b',
                    '--landing-cta-text' => '#25453d',
                    '--landing-surface' => '#eaf2ee',
                    '--landing-footer' => '#1d3f37',
                    '--landing-hero-from' => '#234b42',
                    '--landing-border' => '#dce7e2',
                ],
            ],
            'noche' => [
                'label' => 'Azul noche',
                'colors' => [
                    '--landing-bg' => '#eef2f7',
                    '--landing-ink' => '#0b1220',
                    '--landing-primary' => '#1e293b',
                    '--landing-primary-deep' => '#0f172a',
                    '--landing-primary-soft' => '#e2e8f0',
                    '--landing-accent' => '#38bdf8',
                    '--landing-accent-strong' => '#0ea5e9',
                    '--landing-muted' => '#64748b',
                    '--landing-cta' => '#38bdf8',
                    '--landing-cta-text' => '#0b1220',
                    '--landing-surface' => '#e2e8f0',
                    '--landing-footer' => '#020617',
                    '--landing-hero-from' => '#0f172a',
                    '--landing-border' => '#cbd5e1',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string>
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        if (empty($stored) || ! is_array($stored)) {
            return $defaults;
        }

        $merged = $defaults;
        foreach ($stored as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
