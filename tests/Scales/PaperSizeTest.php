<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Scales\PaperSize;

it('knows ISO 216 and ISO 269 sizes', function (string $name, string $expected) {
    expect(PaperSize::parse($name)->toString())->toBe($expected);
})->with([
    ['A0', '841 × 1189 mm'],
    ['A4', '210 × 297 mm'],
    ['a5', '148 × 210 mm'],
    ['A10', '26 × 37 mm'],
    ['B5', '176 × 250 mm'],
    ['C4', '229 × 324 mm'],
    ['C6 envelope', '114 × 162 mm'],
    ['A4 paper', '210 × 297 mm'],
]);

it('knows North American sizes', function (string $name, string $expected) {
    expect(PaperSize::parse($name)->toString())->toBe($expected);
})->with([
    ['Letter', '8.5 × 11 in'],
    ['US Letter', '8.5 × 11 in'],
    ['legal', '8.5 × 14 in'],
    ['Tabloid', '11 × 17 in'],
    ['Executive', '7.25 × 10.5 in'],
    ['ANSI D', '22 × 34 in'],
]);

it('displays in other units', function () {
    expect(PaperSize::parse('A4')->in('in')->toString())->toBe('8.27 × 11.69 in')
        ->and(PaperSize::parse('Letter')->in('mm')->toString())->toBe('215.9 × 279.4 mm')
        ->and(PaperSize::parse('A4')->in('centimetres')->width()->toString())->toBe('21 cm')
        ->and(PaperSize::parse('A0')->area()->to('m²')->toString())->toBe('0.999949 m²');
});

it('does not parse unknown names', function () {
    expect(PaperSize::tryParse('A11'))->toBeNull()
        ->and(PaperSize::tryParse('foolscap folio'))->toBeNull()
        ->and(fn () => PaperSize::parse('Z9'))->toThrow(ParseException::class);
});
