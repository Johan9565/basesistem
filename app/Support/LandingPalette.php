<?php

namespace App\Support;

class LandingPalette
{
    /**
     * Default GestionDesk palette for the public landing page.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            '--landing-bg' => '#F4F6F8',
            '--landing-ink' => '#1A0C48',
            '--landing-primary' => '#311B92',
            '--landing-primary-deep' => '#1F1060',
            '--landing-primary-soft' => '#EDE7F6',
            '--landing-accent' => '#00E5FF',
            '--landing-accent-strong' => '#00B4D8',
            '--landing-success' => '#00E676',
            '--landing-muted' => '#5C5470',
            '--landing-cta' => '#00E5FF',
            '--landing-cta-text' => '#1A0C48',
            '--landing-surface' => '#FFFFFF',
            '--landing-footer' => '#15093D',
            '--landing-hero-from' => '#311B92',
            '--landing-border' => '#E0E3EB',
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
            'gestiondesk' => [
                'label' => 'GestionDesk Oficial (Púrpura & Cian)',
                'colors' => self::defaults(),
            ],
            'neon_night' => [
                'label' => 'Neon Night',
                'colors' => [
                    '--landing-bg' => '#0F0826',
                    '--landing-ink' => '#FFFFFF',
                    '--landing-primary' => '#311B92',
                    '--landing-primary-deep' => '#15093D',
                    '--landing-primary-soft' => '#241468',
                    '--landing-accent' => '#00E5FF',
                    '--landing-accent-strong' => '#00E676',
                    '--landing-success' => '#00E676',
                    '--landing-muted' => '#9E94B8',
                    '--landing-cta' => '#00E5FF',
                    '--landing-cta-text' => '#15093D',
                    '--landing-surface' => '#1A0C48',
                    '--landing-footer' => '#0A041A',
                    '--landing-hero-from' => '#1F1060',
                    '--landing-border' => '#3B2682',
                ],
            ],
            'verde_menta' => [
                'label' => 'Menta Neón & Púrpura',
                'colors' => [
                    '--landing-bg' => '#F4F6F8',
                    '--landing-ink' => '#15093D',
                    '--landing-primary' => '#311B92',
                    '--landing-primary-deep' => '#1A0C48',
                    '--landing-primary-soft' => '#E8F5E9',
                    '--landing-accent' => '#00E676',
                    '--landing-accent-strong' => '#00C853',
                    '--landing-success' => '#00E676',
                    '--landing-muted' => '#5C5470',
                    '--landing-cta' => '#00E676',
                    '--landing-cta-text' => '#15093D',
                    '--landing-surface' => '#FFFFFF',
                    '--landing-footer' => '#15093D',
                    '--landing-hero-from' => '#311B92',
                    '--landing-border' => '#D5E6DC',
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
