<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;
use Unitary\Units\Area;
use Unitary\Units\Length;

/**
 * ISO 216 (A, B), ISO 269 (C envelope) and North American paper sizes, in
 * portrait orientation (width ≤ height).
 */
final readonly class PaperSize implements Scale
{
    /** ISO sizes in millimeters, per ISO 216 and ISO 269. */
    private const ISO = [
        'A0' => [841, 1189], 'A1' => [594, 841], 'A2' => [420, 594], 'A3' => [297, 420], 'A4' => [210, 297],
        'A5' => [148, 210], 'A6' => [105, 148], 'A7' => [74, 105], 'A8' => [52, 74], 'A9' => [37, 52], 'A10' => [26, 37],
        'B0' => [1000, 1414], 'B1' => [707, 1000], 'B2' => [500, 707], 'B3' => [353, 500], 'B4' => [250, 353],
        'B5' => [176, 250], 'B6' => [125, 176], 'B7' => [88, 125], 'B8' => [62, 88], 'B9' => [44, 62], 'B10' => [31, 44],
        'C0' => [917, 1297], 'C1' => [648, 917], 'C2' => [458, 648], 'C3' => [324, 458], 'C4' => [229, 324],
        'C5' => [162, 229], 'C6' => [114, 162], 'C7' => [81, 114], 'C8' => [57, 81], 'C9' => [40, 57], 'C10' => [28, 40],
    ];

    /** North American sizes in inches (ANSI/ASME Y14.1 for ANSI A–E). */
    private const NORTH_AMERICAN = [
        'Letter' => ['8.5', '11'], 'Legal' => ['8.5', '14'], 'Tabloid' => ['11', '17'], 'Ledger' => ['11', '17'],
        'Executive' => ['7.25', '10.5'], 'Junior Legal' => ['5', '8'], 'Half Letter' => ['5.5', '8.5'],
        'ANSI A' => ['8.5', '11'], 'ANSI B' => ['11', '17'], 'ANSI C' => ['17', '22'], 'ANSI D' => ['22', '34'], 'ANSI E' => ['34', '44'],
    ];

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $height
     * @param  \Unitary\Quantity<\Unitary\Unit>  $width
     */
    private function __construct(
        public string $name,
        private Quantity $width,
        private Quantity $height,
        private ?Unit $displayUnit = null,
    ) {
    }

    public static function parse(string $name): self
    {
        return self::tryParse($name) ?? throw ParseException::cannotParse($name, 'paper size');
    }

    public static function tryParse(string $name): ?self
    {
        $text = trim((string) preg_replace('/\s+/', ' ', preg_replace('/\b(paper|sheet|size|envelope)\b/i', '', $name) ?? ''));
        $upper = strtoupper($text);

        if (isset(self::ISO[$upper])) {
            [$width, $height] = self::ISO[$upper];

            return new self($upper, new Quantity($width, Length::Millimeter), new Quantity($height, Length::Millimeter));
        }

        foreach (self::NORTH_AMERICAN as $key => [$width, $height]) {
            if (strcasecmp($key, $text) === 0 || strcasecmp(str_replace('ANSI ', 'ANSI', $key), $text) === 0
                || ($key === 'Letter' && strcasecmp($text, 'US Letter') === 0) || ($key === 'Legal' && strcasecmp($text, 'US Legal') === 0)) {
                return new self($key, new Quantity($width, Length::Inch), new Quantity($height, Length::Inch));
            }
        }

        return null;
    }

    /**
     * All known size names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [...array_keys(self::ISO), ...array_keys(self::NORTH_AMERICAN)];
    }

    /** The same paper, displayed in another length unit ("A4 in inches"). */
    public function in(Unit|string $unit): self
    {
        $unit = Unitary::converter()->units()->resolve($unit, Length::Meter->dimension());

        return new self($this->name, $this->width, $this->height, $this->width->to($unit)->unit());
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function width(): Quantity
    {
        return $this->displayUnit === null ? $this->width : $this->width->to($this->displayUnit);
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function height(): Quantity
    {
        return $this->displayUnit === null ? $this->height : $this->height->to($this->displayUnit);
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function area(): Quantity
    {
        return new Quantity(
            $this->width->to(Length::Millimeter)->value()->multipliedBy($this->height->to(Length::Millimeter)->value()),
            Area::SquareMillimeter,
        );
    }

    public function toString(): string
    {
        $width = $this->width();

        return sprintf(
            '%s × %s %s',
            $width->toDecimal(2)->strippedOfTrailingZeros(),
            $this->height()->toDecimal(2)->strippedOfTrailingZeros(),
            $width->unit()->symbol(),
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{name: string, width: string, height: string, unit: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'width' => (string) $this->width()->toDecimal(),
            'height' => (string) $this->height()->toDecimal(),
            'unit' => $this->width()->unit()->symbol(),
        ];
    }
}
