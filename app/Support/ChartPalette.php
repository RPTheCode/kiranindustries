<?php

namespace App\Support;

/**
 * Balanced chart colors: clear on white, not neon.
 */
final class ChartPalette
{
    /** Tailwind 500 — readable contrast without harsh brightness. */
    private const SLICE = [
        '#6366f1', // indigo-500
        '#3b82f6', // blue-500
        '#0ea5e9', // sky-500
        '#14b8a6', // teal-500
        '#10b981', // emerald-500
        '#22c55e', // green-500
        '#84cc16', // lime-500
        '#eab308', // yellow-500
        '#f59e0b', // amber-500
        '#f97316', // orange-500
    ];

    /** "Other departments" slice — distinct violet, not gray. */
    private const OTHERS = '#8b5cf6'; // violet-500

    public static function slice(int $index): string
    {
        return self::SLICE[$index % count(self::SLICE)];
    }

    public static function department(int $index): string
    {
        return self::slice($index);
    }

    public static function category(int $index): string
    {
        return self::slice($index);
    }

    public static function others(): string
    {
        return self::OTHERS;
    }
}
