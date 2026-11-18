<?php
namespace App\Services\Signatures;

class CanonicalContentSerializer
{
    public function serialize(array $data): string
    {
        $sort = function (array $value) use (&$sort): array {
            if (!array_is_list($value)) ksort($value, SORT_STRING);
            foreach ($value as &$item) if (is_array($item)) $item = $sort($item);
            return $value;
        };
        return json_encode($sort($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
