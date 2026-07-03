<?php

namespace App\Services;

use App\Models\Setting;

/** Provider global de IA para geração de imagem (configurável pelo admin). */
class AiProvider
{
    public const SETTING_KEY = 'ai_image_provider';

    public const GEMINI = 'gemini-2.5-flash-image';

    public const GPT_IMAGE_LOW = 'gpt-image-low';

    /** @return array<int, array{value:string, label:string}> */
    public static function options(): array
    {
        return [
            ['value' => self::GEMINI, 'label' => 'Gemini 2.5 Flash Image'],
            ['value' => self::GPT_IMAGE_LOW, 'label' => 'GPT Image (low)'],
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::options(), 'value');
    }

    public static function current(): string
    {
        return Setting::get(self::SETTING_KEY, self::GEMINI);
    }
}
