<?php

declare(strict_types=1);

/**
 * Runs PHPStan with the extension over tests/PHPStan/data and checks every
 * inferred type and reported error, line by line.
 */
function analyse(): array
{
    static $lines = null;

    if ($lines !== null) {
        return $lines;
    }

    $root = dirname(__DIR__, 2);
    $command = sprintf(
        '%s analyse -c %s --no-progress --error-format=json --memory-limit=2G 2>/dev/null',
        escapeshellarg($root.'/vendor/bin/phpstan'),
        escapeshellarg(__DIR__.'/phpstan.neon'),
    );

    $output = json_decode((string) shell_exec($command), true);
    $lines = [];

    foreach ($output['files'] ?? [] as $messages) {
        foreach ($messages['messages'] as $message) {
            $lines[$message['line']][] = $message['message'];
        }
    }

    return $lines;
}

it('infers precise types from literals and unit maths', function (int $line, string $type) {
    expect(analyse()[$line] ?? [])->toContain("Dumped type: {$type}");
})->with([
    [13, 'Unitary\Quantity<Unitary\Units\Mass::Kilogram>'],
    [14, 'Unitary\Quantity<Unitary\Units\Length::Inch>'],
    [15, 'Unitary\Quantity<Unitary\Currency\Currency::USD>'],
    [16, 'Unitary\Quantity<Unitary\Currency\Currency::GBP>'],
    [17, 'Unitary\Color\Hex'],
    [18, 'Unitary\Time\ZonedTime'],
    [19, 'Unitary\Quantity<Unitary\Units\Length::Foot>'],
    [20, 'Unitary\Quantity<Unitary\Units\Speed::KilometerPerHour>'],
    [21, 'Unitary\Quantity<Unitary\Units\Area::SquareMeter>'],
    [22, 'Unitary\Quantity<Unitary\Units\Volume::CubicMeter>'],
    [23, 'Unitary\Quantity<Unitary\Units\Speed::MeterPerSecond>'],
    [24, 'Unitary\Quantity<Unitary\Units\Mass::Ounce>'],
    [40, 'Unitary\Quantity<Unitary\Units\TemperatureDifference::Celsius>'],
    [41, 'Unitary\Quantity<Unitary\Units\Temperature::Fahrenheit>'],
    [42, 'Unitary\Quantity<Unitary\Units\TemperatureDifference::Fahrenheit>'],
    [43, 'Unitary\Quantity<Unitary\Units\Temperature::Celsius>'],
])->group('phpstan');

it('reports unit mistakes at analysis time', function (int $line, string $message) {
    expect(analyse()[$line] ?? [])->toContain($message);
})->with([
    [26, 'Unitary: Unable to parse "5 florbs" as a quantity, color, time, coordinate or scale.'],
    [27, 'Unitary: Unknown unit "blorps".'],
    [28, 'Unitary: Cannot convert kg (mass) to m (length).'],
    [32, 'Unitary: Cannot add mass (kg) to length (m).'],
    [33, 'Unitary: Cannot convert length (m) to mass (kg).'],
    [35, 'Unitary: Cannot compare length (m) with time (s).'],
    [38, 'Unitary: Unknown unit "florbs".'],
])->group('phpstan');

it('stays quiet about valid code', function () {
    $lines = analyse();

    foreach ([34, 36, 37, 40, 41, 42, 43] as $line) {
        if ($line >= 40) {
            expect(array_filter($lines[$line] ?? [], fn (string $m) => ! str_starts_with($m, 'Dumped type:')))->toBe([]);

            continue;
        }

        expect($lines[$line] ?? [])->toBe([]);
    }
})->group('phpstan');
