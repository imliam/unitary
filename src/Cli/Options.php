<?php

declare(strict_types=1);

namespace Unitary\Cli;

use InvalidArgumentException;
use Unitary\UnitDisplay;

/**
 * Parsed command-line flags. Anything that isn't a "--flag" (or -h/-V) is
 * positional, so negative numbers like "-40 °F to °C" pass straight through.
 *
 * @internal
 */
final readonly class Options
{
    private const RATES = ['ecb', 'frankfurter', 'exchangerate-api', 'openexchangerates'];

    /**
     * @param  list<string>  $arguments
     * @param  non-negative-int|null  $precision
     */
    public function __construct(
        public array $arguments = [],
        public bool $json = false,
        public bool $scientific = false,
        public bool $noColor = false,
        public bool $help = false,
        public bool $version = false,
        public ?int $precision = null,
        public ?string $rates = null,
        public bool $plain = false,
        public ?string $locale = null,
        public bool $quiet = false,
        public ?string $usage = null,
        public ?string $region = null,
        public ?UnitDisplay $display = null,
    ) {
    }

    /**
     * @param  list<string>  $argv  Arguments without the script name.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(array $argv): self
    {
        $arguments = [];
        $flags = ['json' => false, 'scientific' => false, 'no-color' => false, 'help' => false, 'version' => false, 'plain' => false, 'quiet' => false];
        $values = ['precision' => null, 'rates' => null, 'locale' => null, 'usage' => null, 'region' => null, 'display' => null];
        $optionsEnded = false;

        for ($i = 0, $count = count($argv); $i < $count; $i++) {
            $argument = $argv[$i];

            if ($optionsEnded || ! (str_starts_with($argument, '--') || in_array($argument, ['-h', '-V', '-q'], true))) {
                $arguments[] = $argument;

                continue;
            }

            if ($argument === '--') {
                $optionsEnded = true;

                continue;
            }

            $argument = match ($argument) {
                '-h' => '--help',
                '-V' => '--version',
                '-q' => '--quiet',
                default => $argument,
            };

            $parts = explode('=', substr($argument, 2), 2);
            $name = $parts[0];
            $value = $parts[1] ?? null;

            if (array_key_exists($name, $flags)) {
                $flags[$name] = true;

                continue;
            }

            if (! array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Unknown option --{$name}");
            }

            $value ??= $argv[++$i] ?? throw new InvalidArgumentException("The --{$name} option needs a value");
            $values[$name] = $value;
        }

        $precision = $values['precision'];

        if ($precision !== null && ! preg_match('/^\d+$/', $precision)) {
            throw new InvalidArgumentException('--precision must be a whole number');
        }

        $rates = $values['rates'] !== null ? strtolower($values['rates']) : null;

        if ($rates !== null && ! in_array($rates, self::RATES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown --rates provider "%s" (use %s)', $rates, implode(', ', self::RATES)));
        }

        $display = $values['display'] !== null ? UnitDisplay::tryFrom(strtolower($values['display'])) : null;

        if ($values['display'] !== null && $display === null) {
            throw new InvalidArgumentException('--display must be narrow, short or long');
        }

        return new self(
            arguments: $arguments,
            json: $flags['json'],
            scientific: $flags['scientific'],
            noColor: $flags['no-color'],
            help: $flags['help'],
            version: $flags['version'],
            precision: $precision !== null ? max(0, (int) $precision) : null,
            rates: $rates,
            plain: $flags['plain'],
            locale: $values['locale'],
            quiet: $flags['quiet'],
            usage: $values['usage'],
            region: $values['region'],
            display: $display,
        );
    }
}
