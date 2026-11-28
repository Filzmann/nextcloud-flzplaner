<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Vendor\Filzmann\Shared\Model;

trait ModelApiTrait {
    public static function get(mixed $data) {
        if ($data === null) {
            return null;
        }

        if (is_object($data) && is_a($data, static::class)) {
            return $data;
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException(static::class . '::get() erwartet Modelldaten als Array oder Objekt.');
        }

        if (method_exists(static::class, 'fromArray')) {
            return static::fromArray($data);
        }

        if (method_exists(static::class, 'fromRow')) {
            return static::fromRow($data);
        }

        return new static($data);
    }

    public static function get_all(array $items = []): array {
        return array_values(array_filter(array_map(
            static fn($item) => static::get($item),
            $items
        )));
    }

    public function toArray(): array {
        throw new \RuntimeException(static::class . ' muss toArray() implementieren.');
    }

    public function to_array(): array {
        return $this->toArray();
    }

    public function save(): int {
        throw new \RuntimeException(static::class . ' kann nicht direkt gespeichert werden.');
    }
}
