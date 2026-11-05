<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Units\Speed;

/**
 * The Beaufort wind force scale, using the WMO empirical relation
 * v = 0.836 × B^(3/2) m/s (measured 10 m above the surface).
 */
final readonly class Beaufort implements Scale
{
    private const DESCRIPTIONS = [
        'Calm', 'Light air', 'Light breeze', 'Gentle breeze', 'Moderate breeze', 'Fresh breeze', 'Strong breeze',
        'Near gale', 'Gale', 'Strong gale', 'Storm', 'Violent storm', 'Hurricane force',
    ];

    private function __construct(public int $force)
    {
    }

    public static function of(int $force): self
    {
        if ($force < 0 || $force > 12) {
            throw ParseException::cannotParse((string) $force, 'Beaufort force', 'the scale runs from 0 to 12');
        }

        return new self($force);
    }

    public static function tryParse(string $input): ?self
    {
        if (! preg_match('/^(?:(?:beaufort|bft|wind\s+force|force)\s*(?<a>\d{1,2})|(?<b>\d{1,2})\s*(?:beaufort|bft|on the beaufort scale))$/i', trim($input), $matches)) {
            return null;
        }

        $force = (int) ($matches['b'] ?? $matches['a']);

        return $force <= 12 ? new self($force) : null;
    }

    /** The Beaufort number whose band contains the speed. */
    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $speed
     */
    public static function fromSpeed(Quantity $speed): self
    {
        $metersPerSecond = max(0.0, $speed->to(Speed::MeterPerSecond)->toFloat());

        return new self(min(12, (int) round(($metersPerSecond / 0.836) ** (2 / 3))));
    }

    /** The nominal (mid-band) wind speed. */
    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function speed(): Quantity
    {
        return new Quantity(0.836 * $this->force ** 1.5, Speed::MeterPerSecond);
    }

    public function description(): string
    {
        return self::DESCRIPTIONS[$this->force];
    }

    public function toString(): string
    {
        return "Beaufort {$this->force} ({$this->description()})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{force: int, description: string, speed_ms: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'force' => $this->force,
            'description' => $this->description(),
            'speed_ms' => (string) $this->speed()->toDecimal(1),
        ];
    }
}
