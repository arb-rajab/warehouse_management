<?php

use App\Console\Concerns\ReadsTextInput;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * A command whose input is the given values, to read them through the trait.
 *
 * @param  array<string, mixed>  $values
 */
function textInputCommand(array $values): object
{
    $command = new class extends Command
    {
        use ReadsTextInput;

        public function argumentText(string $key): string
        {
            return $this->textArgument($key);
        }

        public function optionText(string $key): ?string
        {
            return $this->textOption($key);
        }
    };

    $definition = new InputDefinition([
        new InputArgument('name', InputArgument::OPTIONAL),
        new InputArgument('names', InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
        new InputOption('title', null, InputOption::VALUE_REQUIRED),
        new InputOption('tags', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        new InputOption('flag', null, InputOption::VALUE_NONE),
    ]);
    $command->setInput(new ArrayInput($values, $definition));

    return $command;
}

test('an argument and an option given as text are read as that text', function () {
    $command = textInputCommand(['name' => 'admin@example.com', '--title' => 'Hello']);

    expect($command->argumentText('name'))->toBe('admin@example.com')
        ->and($command->optionText('title'))->toBe('Hello');
});

test('a number given programmatically is read as its text', function () {
    expect(textInputCommand(['--title' => 12])->optionText('title'))->toBe('12')
        ->and(textInputCommand(['name' => 1.5])->argumentText('name'))->toBe('1.5');
});

test('an option that was not given is null', function () {
    expect(textInputCommand([])->optionText('title'))->toBeNull();
});

test('a missing argument is refused', function () {
    expect(fn () => textInputCommand([])->argumentText('name'))
        ->toThrow(InvalidArgumentException::class, 'The "name" argument is required.');
});

test('an array or a bare flag is refused instead of being cast to text', function (string $method, string $key, array $values, string $message) {
    expect(fn () => textInputCommand($values)->{$method}($key))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'array argument' => ['argumentText', 'names', ['names' => ['a', 'b']], 'The "names" argument takes one text value.'],
    'array option' => ['optionText', 'tags', ['--tags' => ['a', 'b']], 'The --tags option takes one text value.'],
    'flag option' => ['optionText', 'flag', ['--flag' => true], 'The --flag option takes one text value.'],
]);
