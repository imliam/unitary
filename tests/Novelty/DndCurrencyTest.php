<?php

declare(strict_types=1);

use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Unitary;
use Unitary\Units\Novelty\DndCurrency;

use function Unitary\convert;

it('converts D&D coinage', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['5 gp in cp', '500 cp'],
    ['500 cp in gp', '5 gp'],
    ['1 pp to ep', '20 ep'],
    ['3 gp 5 sp in sp', '35 sp'],
    ['5 gp + 3 sp', '53 sp'],
    ['2 platinum pieces in silver', '200 sp'],
    ['1 pp 5 gp in gold', '15 gp'],
    ['7 gold pieces in copper', '700 cp'],
]);

it('keeps real units first for bare symbols', function () {
    expect(convert('5 cp')->dimension()->value)->toBe('luminous_intensity')
        ->and(convert('5 sp')->dimension()->value)->toBe('solid_angle');
});

it('breaks down and humanizes coin totals', function () {
    expect(convert(1234, DndCurrency::CopperPiece)->breakdown()->toString())->toBe('1 pp 2 gp 3 sp 4 cp')
        ->and(convert('1234 cp in gp')->humanize()->toString())->toBe('1.234 pp');
});

it('works in the calculator', function () {
    expect((string) Unitary::evaluate('3 potions at 50 gp each + 2 gp 5 sp'))->toBe('152.5 gp')
        ->and((string) Unitary::evaluate('150 gp / 4 in sp'))->toBe('375 sp');
});

it('never converts to real money', function () {
    convert('5 gp in USD');
})->throws(IncompatibleUnitsException::class);
