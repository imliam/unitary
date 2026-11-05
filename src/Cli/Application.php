<?php

declare(strict_types=1);

namespace Unitary\Cli;

use Brick\Math\RoundingMode;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Throwable;
use Unitary\Breakdown;
use Unitary\Calc\Calculator;
use Unitary\Color\Color;
use Unitary\Converter;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Currency\Providers\EuropeanCentralBank;
use Unitary\Currency\Providers\ExchangeRateApi;
use Unitary\Currency\Providers\Frankfurter;
use Unitary\Currency\Providers\OpenExchangeRates;
use Unitary\Dimension;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Exceptions\ParseException;
use Unitary\Exceptions\UnknownUnitException;
use Unitary\Geo\Coordinate;
use Unitary\Preferences\UnitPreferences;
use Unitary\Quantity;
use Unitary\Scales\Scale;
use Unitary\Time\ZonedTime;
use Unitary\Unit;
use Unitary\UnitRegistry;

/**
 * The `unitary` command:
 *
 *     unitary "£23 in USD"
 *     unitary 5 ft 11 in to cm --json
 *     unitary units length
 *     unitary 80 km --usage=road --region=GB
 *     unitary eval budget.txt
 *     echo "100 km to mi" | unitary
 */
final class Application
{
    private const HELP = <<<'TXT'
        Unitary: convert units, currencies, colors and times.

        Usage:
          unitary <expression> [options]      e.g. unitary "£23 in USD", unitary 5 ft 11 in to cm
          unitary units [dimension]           List units, optionally for one dimension
          unitary usages [dimension]          List the usages --usage accepts (road, person-height...)
          unitary check <expression>          Exit 0 if the input means something, 1 if not (offline)
          unitary dimensions                  List dimensions
          unitary eval [file|-]               Evaluate each line of a file (or stdin)
          <lines> | unitary                   Convert each line from stdin

        Options:
          --json             Machine-readable output
          --precision=N      Round to N decimal places
          --plain            Print raw values without digit grouping (parseable)
          -q, --quiet        With check: print nothing, just set the exit code
          --locale=LOCALE    Number separators for a locale, e.g. de or fr_FR (default: $LANG)
          --usage=USAGE      Use the units the region uses for this, e.g. road, person-height, weather
          --region=REGION    Region for --usage, e.g. GB (default: from the locale)
          --display=WIDTH    narrow (2″), short (2 in) or long (2 inches), in the locale's language
          --scientific       Scientific notation (N significant digits with --precision)
          --rates=PROVIDER   ecb (default), frankfurter, exchangerate-api, openexchangerates
          --no-color         Disable colored output (also respects NO_COLOR)
          -h, --help         Show this help
          -V, --version      Show the version

        Environment:
          UNITARY_RATES               Default rates provider
          OPENEXCHANGERATES_APP_ID    App ID for --rates=openexchangerates
          EXCHANGERATE_API_KEY        Optional key for --rates=exchangerate-api
        TXT;

    /** @var array<string, string> */
    private readonly array $env;

    /** @var resource */
    private $stdin;

    private Options $options;

    private ?RecordingExchangeRates $recorder = null;

    /**
     * @param  array<string, string>|null  $env  Defaults to getenv().
     * @param  resource|null  $stdin  Defaults to STDIN.
     * @param  (\Closure(string): (Quantity<\Unitary\Unit>|Color|ZonedTime|Coordinate|Scale))|null  $evaluator  Evaluates `eval` lines; defaults to a Calculator per sheet.
     */
    public function __construct(
        private ?Converter $converter = null,
        ?array $env = null,
        $stdin = null,
        private readonly ?\Closure $evaluator = null,
    ) {
        $this->env = $env ?? getenv();
        $this->stdin = $stdin ?? STDIN;
        $this->options = new Options();
    }

    /**
     * @param  list<string>  $argv  Including the script name, as in $argv.
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    public function run(array $argv, $stdout, $stderr): int
    {
        try {
            $this->options = Options::parse(array_slice($argv, 1));
        } catch (InvalidArgumentException $e) {
            $this->error($stderr, $e->getMessage());
            fwrite($stderr, "Run `unitary --help` for usage.\n");

            return 1;
        }

        if ($this->options->help) {
            return $this->help($stdout);
        }

        if ($this->options->version) {
            fwrite($stdout, 'unitary '.$this->version()."\n");

            return 0;
        }

        $arguments = $this->options->arguments;

        try {
            return match ($arguments[0] ?? null) {
                'help' => $this->help($stdout),
                'units' => $this->units($stdout, implode(' ', array_slice($arguments, 1))),
                'usages' => $this->usages($stdout, implode(' ', array_slice($arguments, 1))),
                'dimensions' => $this->dimensions($stdout),
                'eval' => $this->evaluate($stdout, $stderr, $arguments[1] ?? '-'),
                'check' => $this->check($stdout, implode(' ', array_slice($arguments, 1))),
                null => $this->fromStdin($stdout, $stderr),
                default => $this->convertOne($stdout, $stderr, implode(' ', $arguments)),
            };
        } catch (Throwable $e) {
            $this->error($stderr, $e->getMessage());

            return 1;
        }
    }

    /**
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    private function convertOne($stdout, $stderr, string $expression): int
    {
        try {
            $result = $this->convert($expression);
        } catch (Throwable $e) {
            $this->failure($stderr, $expression, $e);

            return 1;
        }

        fwrite($stdout, $this->options->json
            ? $this->json($this->describe($result), pretty: true)."\n"
            : $this->style($this->render($result), '1', $stdout)."\n");

        return 0;
    }

    /**
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    private function fromStdin($stdout, $stderr): int
    {
        if (stream_isatty($this->stdin)) {
            fwrite($stderr, self::HELP."\n");

            return 1;
        }

        $failed = false;
        $number = 0;

        while (($line = fgets($this->stdin)) !== false) {
            $number++;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $result = $this->convert($line);
            } catch (Throwable $e) {
                $failed = true;
                $this->failure($stderr, $line, $e, "line {$number}: ");

                continue;
            }

            fwrite($stdout, ($this->options->json ? $this->json($this->describe($result)) : $this->render($result))."\n");
        }

        return $failed ? 1 : 0;
    }

    /**
     * Evaluates a file line by line, printing each line next to its result.
     *
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    private function evaluate($stdout, $stderr, string $file): int
    {
        $contents = match (true) {
            $file === '-' => stream_get_contents($this->stdin),
            is_file($file) && is_readable($file) => @file_get_contents($file),
            default => false,
        };

        if ($contents === false) {
            $this->error($stderr, "Cannot read {$file}");

            return 1;
        }

        $lines = preg_split('/\R/', rtrim($contents, "\r\n")) ?: [];
        $rows = [];
        $failed = false;

        // One calculator per sheet, so variables, prev and sum carry between lines.
        $calculator = new Calculator($this->converter());

        foreach ($lines as $line) {
            $trimmed = trim($line);

            try {
                $rows[] = ['line' => $line, 'result' => $this->evaluateLine($trimmed, $calculator), 'error' => null];
            } catch (Throwable $e) {
                $failed = true;
                $rows[] = ['line' => $line, 'result' => null, 'error' => $e->getMessage()];
            }
        }

        if ($this->options->json) {
            fwrite($stdout, $this->json(array_map(fn (array $row) => [
                'line' => $row['line'],
                'result' => $row['result'] !== null ? $this->describe($row['result']) : null,
                'error' => $row['error'],
            ], $rows), pretty: true)."\n");

            return $failed ? 1 : 0;
        }

        $width = min(60, max(0, ...array_map(fn (array $row) => mb_strwidth($row['line']), $rows)));

        foreach ($rows as $row) {
            if ($row['result'] === null && $row['error'] === null) {
                fwrite($stdout, rtrim($row['line'])."\n");

                continue;
            }

            $padding = str_repeat(' ', max(0, $width - mb_strwidth($row['line'])));
            $value = $row['result'] !== null
                ? $this->style($this->render($row['result']), '36', $stdout)
                : $this->style('⚠ '.$row['error'], '31', $stdout);

            fwrite($stdout, $row['line'].$padding.'   '.$value."\n");
        }

        return $failed ? 1 : 0;
    }

    /**
     * Uses the injected evaluator when given, otherwise the sheet's calculator.
     * Returns null for blank lines and comments.
     *
     * @return Quantity<\Unitary\Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    private function evaluateLine(string $line, Calculator $calculator): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        if ($this->evaluator !== null) {
            return $line === '' || preg_match('/^(?:#(?:\s|$)|\/\/)/u', $line) ? null : ($this->evaluator)($line);
        }

        return $calculator->evaluate($line);
    }

    /**
     * @param  resource  $stdout
     */
    private function units($stdout, string $dimensionText): int
    {
        $registry = $this->converter()->units();
        $dimensions = Dimension::cases();

        if ($dimensionText !== '') {
            $dimensions = [$this->dimension($dimensionText)];
        }

        $groups = [];

        foreach ($dimensions as $dimension) {
            $groups[] = [
                'dimension' => $dimension,
                'units' => array_map(fn (Unit $unit) => [
                    'symbol' => $unit->symbol(),
                    'name' => $unit->name(),
                    'aliases' => array_values(array_filter(
                        $unit->aliases(),
                        fn (string $alias) => $alias !== $unit->name() && $alias !== $unit->symbol(),
                    )),
                ], $registry->all($dimension)),
            ];
        }

        if ($this->options->json) {
            fwrite($stdout, $this->json(array_map(fn (array $group) => [
                'dimension' => $group['dimension']->value,
                'units' => $group['units'],
            ], $groups), pretty: true)."\n");

            return 0;
        }

        foreach ($groups as $index => $group) {
            $symbolWidth = max(0, ...array_map(fn (array $unit) => mb_strwidth($unit['symbol']), $group['units']));
            $nameWidth = max(0, ...array_map(fn (array $unit) => mb_strwidth($unit['name']), $group['units']));

            fwrite($stdout, ($index > 0 ? "\n" : '').$this->style(ucfirst($group['dimension']->label()), '1', $stdout).' ('.count($group['units']).")\n");

            foreach ($group['units'] as $unit) {
                fwrite($stdout, rtrim(sprintf(
                    '  %s  %s  %s',
                    $unit['symbol'].str_repeat(' ', $symbolWidth - mb_strwidth($unit['symbol'])),
                    $unit['name'].str_repeat(' ', $nameWidth - mb_strwidth($unit['name'])),
                    $this->style(implode(', ', $unit['aliases']), '2', $stdout),
                ))."\n");
            }
        }

        return 0;
    }

    /**
     * @param  resource  $stdout
     */
    private function usages($stdout, string $dimensionText): int
    {
        $dimensions = $dimensionText === '' ? Dimension::cases() : [$this->dimension($dimensionText)];
        $rows = [];

        foreach ($dimensions as $dimension) {
            $usages = UnitPreferences::usages($dimension);

            if ($usages !== []) {
                $rows[$dimension->value] = $usages;
            }
        }

        if ($this->options->json) {
            fwrite($stdout, $this->json($rows, pretty: true)."\n");

            return 0;
        }

        $width = max([0, ...array_map('strlen', array_keys($rows))]);

        foreach ($rows as $dimension => $usages) {
            fwrite($stdout, sprintf("%-{$width}s  %s\n", $dimension, implode(', ', $usages)));
        }

        return 0;
    }

    /**
     * @param  resource  $stdout
     */
    private function dimensions($stdout): int
    {
        $registry = $this->converter()->units();
        $rows = array_map(fn (Dimension $dimension) => [
            'dimension' => $dimension->value,
            'label' => $dimension->label(),
            'units' => count($registry->all($dimension)),
        ], array_filter(Dimension::cases(), fn (Dimension $dimension) => $dimension !== Dimension::Derived));

        if ($this->options->json) {
            fwrite($stdout, $this->json($rows, pretty: true)."\n");

            return 0;
        }

        $width = max(...array_map(fn (array $row) => strlen($row['dimension']), $rows));

        foreach ($rows as $row) {
            fwrite($stdout, sprintf("%-{$width}s  %4d units\n", $row['dimension'], $row['units']));
        }

        return 0;
    }

    /**
     * @param  resource  $stdout
     */
    private function help($stdout): int
    {
        fwrite($stdout, self::HELP."\n");

        return 0;
    }

    /**
     * @return Quantity<\Unitary\Unit>|Breakdown|Color|ZonedTime|Coordinate|Scale
     */
    private function convert(string $expression): Quantity|Breakdown|Color|ZonedTime|Coordinate|Scale
    {
        if ($this->recorder !== null) {
            $this->recorder->last = null;
        }

        $result = $this->converter()->convert($expression);

        return $this->options->usage !== null && $result instanceof Quantity
            ? $result->forLocale($this->region(), $this->options->usage)
            : $result;
    }

    /**
     * @param  Quantity<\Unitary\Unit>|Breakdown|Color|ZonedTime|Coordinate|Scale  $result
     */
    private function render(Quantity|Breakdown|Color|ZonedTime|Coordinate|Scale $result): string
    {
        // Mixed units read best with the smallest part rounded: 5 ft 10.87 in.
        if ($result instanceof Breakdown) {
            return $result->toString($this->options->precision ?? 2, $this->options->display === null ? null : $this->locale(), $this->options->display);
        }

        if (! $result instanceof Quantity) {
            return $result->toString();
        }

        $precision = $this->options->precision;

        return match (true) {
            $this->options->scientific => $result->toScientific($precision ?? 10),
            $this->options->plain && $precision !== null => $result->format($precision, '.', ''),
            $this->options->plain => $result->toString(),
            default => $result->format($precision, locale: $this->locale(), display: $this->options->display),
        };
    }

    /**
     * Prints yes/no (or JSON) and exits 0 when the input is understood, 1 when not.
     *
     * @param  resource  $stdout
     */
    private function check($stdout, string $expression): int
    {
        $understood = $this->converter()->understands($expression);

        if ($this->options->json) {
            fwrite($stdout, $this->json(['input' => $expression, 'understood' => $understood])."\n");
        } elseif (! $this->options->quiet) {
            fwrite($stdout, ($understood ? 'yes' : 'no')."\n");
        }

        return $understood ? 0 : 1;
    }

    /**
     * --region, then the region in the locale ("en_GB" → "GB"), else worldwide.
     */
    private function region(): string
    {
        return $this->options->region ?? $this->locale() ?? '001';
    }

    /**
     * --locale, then UNITARY_LOCALE, then the usual POSIX variables ("en_GB.UTF-8" → "en_GB").
     */
    private function locale(): ?string
    {
        foreach ([$this->options->locale, $this->env['UNITARY_LOCALE'] ?? null, $this->env['LC_ALL'] ?? null, $this->env['LC_NUMERIC'] ?? null, $this->env['LANG'] ?? null] as $locale) {
            if ($locale === null || $locale === '') {
                continue;
            }

            $locale = explode('.', $locale)[0];

            return in_array($locale, ['C', 'POSIX'], true) ? null : $locale;
        }

        return null;
    }

    /**
     * @param  Quantity<\Unitary\Unit>|Breakdown|Color|ZonedTime|Coordinate|Scale  $result
     * @return array<array-key, mixed>
     */
    private function describe(Quantity|Breakdown|Color|ZonedTime|Coordinate|Scale $result): array
    {
        if ($result instanceof Breakdown) {
            return ['type' => 'breakdown', 'formatted' => $this->render($result), 'parts' => $result->jsonSerialize()];
        }

        $type = match (true) {
            $result instanceof Quantity => 'quantity',
            $result instanceof Color => 'color',
            $result instanceof Coordinate => 'coordinate',
            $result instanceof Scale => 'scale',
            default => 'time',
        };

        $data = $result->jsonSerialize();
        $description = ['type' => $type, 'formatted' => $this->render($result), ...(is_array($data) ? $data : ['value' => $data])];
        $rate = $this->recorder?->last;

        if ($result instanceof Quantity && $rate !== null) {
            $description['exchange_rate'] = [
                'from' => $rate->from->value,
                'to' => $rate->to->value,
                'rate' => (string) $rate->rate->toScale(10, RoundingMode::HalfUp)->strippedOfTrailingZeros(),
                'source' => $rate->source,
                'date' => $rate->date?->format(DATE_ATOM),
            ];
        }

        return $description;
    }

    private function dimension(string $text): Dimension
    {
        $needle = (string) preg_replace('/[\s_-]+/', '', strtolower($text));

        foreach (Dimension::cases() as $dimension) {
            if (in_array($needle, [str_replace('_', '', $dimension->value), strtolower($dimension->name)], true)) {
                return $dimension;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Unknown dimension "%s". Available: %s',
            $text,
            implode(', ', array_map(fn (Dimension $d) => $d->value, Dimension::cases())),
        ));
    }

    private function converter(): Converter
    {
        if ($this->converter === null) {
            $this->converter = new Converter(UnitRegistry::default(), $this->provider());
        }

        $provider = $this->converter->exchangeRates();

        if ($provider !== null && ! $provider instanceof RecordingExchangeRates) {
            $this->recorder = new RecordingExchangeRates($provider);
            $this->converter->useExchangeRates($this->recorder);
        }

        return $this->converter;
    }

    private function provider(): ExchangeRateProvider
    {
        $cache = new FileRateCache();
        $name = $this->options->rates ?? strtolower($this->env['UNITARY_RATES'] ?? 'ecb');

        return match ($name) {
            'ecb' => new EuropeanCentralBank(cache: $cache),
            'frankfurter' => new Frankfurter(cache: $cache),
            'exchangerate-api' => new ExchangeRateApi(apiKey: $this->env['EXCHANGERATE_API_KEY'] ?? null, cache: $cache),
            'openexchangerates' => new OpenExchangeRates(
                $this->env['OPENEXCHANGERATES_APP_ID'] ?? throw new InvalidArgumentException('Set OPENEXCHANGERATES_APP_ID to use --rates=openexchangerates'),
                cache: $cache,
            ),
            default => throw new InvalidArgumentException("Unknown rates provider \"{$name}\" in UNITARY_RATES"),
        };
    }

    /**
     * @param  resource  $stderr
     */
    private function failure($stderr, string $input, Throwable $e, string $prefix = ''): void
    {
        $message = $e->getMessage();

        if ($e instanceof ExchangeRateUnavailableException && str_contains($message, 'No exchange rate provider')) {
            $message = 'No exchange rate provider is configured; pass --rates=ecb (or another provider)';
        }

        $this->error($stderr, $prefix.$message);

        if ($e instanceof ParseException || $e instanceof UnknownUnitException) {
            foreach ((new Suggestions($this->converter()->units()))->for($input) as $word => $options) {
                fwrite($stderr, $this->style(sprintf(
                    '  did you mean %s instead of "%s"?',
                    implode(', ', array_map(fn (string $option) => "\"{$option}\"", $options)),
                    $word,
                ), '33', $stderr)."\n");
            }
        }
    }

    /**
     * @param  resource  $stderr
     */
    private function error($stderr, string $message): void
    {
        fwrite($stderr, $this->style('error:', '31;1', $stderr).' '.$message."\n");
    }

    /**
     * @param  resource  $stream
     */
    private function style(string $text, string $code, $stream): string
    {
        $enabled = ! $this->options->noColor
            && ! isset($this->env['NO_COLOR'])
            && stream_isatty($stream);

        return $enabled && $text !== '' ? "\033[{$code}m{$text}\033[0m" : $text;
    }

    private function json(mixed $data, bool $pretty = false): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    private function version(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('imliam/unitary')) {
            $version = InstalledVersions::getPrettyVersion('imliam/unitary');

            return $version === null || str_contains($version, 'no-version-set') ? 'dev' : $version;
        }

        return 'dev';
    }
}
