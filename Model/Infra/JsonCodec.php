<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Infra;

/** The only JSON encode/decode helpers in the module: strict encode, forgiving array decode. */
final class JsonCodec
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<mixed> Empty array for null, invalid JSON, or non-array JSON. */
    public static function decodeArray(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
