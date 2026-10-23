<?php

namespace App\Support\RequiredFields;

use App\Models\StoreUser;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rules\RequiredIf;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * The server half of `forms:audit-required`: which fields each form endpoint
 * validates, and how.
 *
 * - A FormRequest's `rules()` is called on an instance built without
 *   validating (`FormRequest::create()`), with the route parameters of a
 *   route that uses it bound to fresh, unsaved models and an unsaved user of
 *   its guard, inside a rolled-back transaction. A failure is recorded as unparsed, never skipped.
 * - An inline `$request->validate([...])`, `$this->validate($request, [...])`
 *   or `Validator::make($data, [...])` in a controller method, or in a
 *   `$this->helper(...)` that method calls (two levels), is read from the
 *   source with nikic/php-parser: string rules exactly, rule objects by name,
 *   and anything that is not a literal array as unparsed.
 *
 * A field is "required" when its rules carry the exact `required` (or
 * `accepted`) token, "conditional" for `required_if`, `required_with`, ...,
 * `Rule::requiredIf()` and a `Rule::when()` that may add `required`, and
 * "optional" otherwise; a rule the parser cannot read (`expr:...`) counts as
 * conditional, so it is never reported as optional. `confirmed` adds `<field>_confirmation` with the
 * field's presence (conditional when the field is optional).
 *
 * @phpstan-type ServerField array{name: string, presence: 'required'|'conditional'|'optional', rules: string, file: string, line: int, derived: bool}
 * @phpstan-type ServerForm array{source: string, file: string, line: int, validated: bool, unparsed: string|null, fields: array<string, ServerField>}
 */
final class ServerRules
{
    /** @var array<string, list<Route>> */
    private array $routesByAction = [];

    /** @var array<string, ServerForm> */
    private array $formRequestCache = [];

    /** @var array<string, ServerForm> */
    private array $actionCache = [];

    /** @var array<string, array<int, Node\Stmt>> */
    private array $astCache = [];

    public function __construct(private readonly string $basePath)
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $this->routesByAction[$route->getActionName()][] = $route;
        }
    }

    /**
     * What the controller action validates.
     *
     * @param  array<string, string>  $input  Literal hidden form values, for section-specific rules.
     * @return ServerForm
     */
    public function forAction(string $controller, string $method, array $input = []): array
    {
        $source = $controller.'@'.$method;
        $key = $source.json_encode($input);
        if (isset($this->actionCache[$key])) {
            return $this->actionCache[$key];
        }

        if (! class_exists($controller)) {
            return $this->actionCache[$key] = $this->unparsedForm($source, "controller {$controller} does not exist");
        }
        if (! method_exists($controller, $method) && str_ends_with($method, 'Method') && method_exists($controller, substr($method, 0, -6))) {
            $method = substr($method, 0, -6);
        }
        if (! method_exists($controller, $method)) {
            return $this->actionCache[$key] = $this->unparsedForm($source, "{$controller}::{$method}() does not exist");
        }

        $reflection = new ReflectionMethod($controller, $method);
        $forms = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
                $forms[] = $this->forFormRequest($type->getName(), $controller.'@'.$method, $input);
            }
        }
        $inline = $this->inlineRules($controller, $method);
        if ($inline !== null) {
            $forms[] = $inline;
        }

        if ($forms === []) {
            return $this->actionCache[$key] = [
                'source' => $source,
                'file' => $this->relative((string) $reflection->getFileName()),
                'line' => (int) $reflection->getStartLine(),
                'validated' => false,
                'unparsed' => null,
                'fields' => [],
            ];
        }

        $merged = $forms[0];
        foreach (array_slice($forms, 1) as $form) {
            $merged['source'] .= ' + '.$form['source'];
            $merged['unparsed'] ??= $form['unparsed'];
            $merged['fields'] = [...$merged['fields'], ...$form['fields']];
        }

        return $this->actionCache[$key] = $merged;
    }

    /**
     * The controller action a form posting to a named route runs. A form
     * often posts to the URI of a GET route's name (`register` names the GET
     * form; the POST on the same URI is unnamed), so the route with the same
     * URI that accepts the form's method wins.
     *
     * @return array{controller: string, method: string}|null
     */
    public function actionForRoute(string $name, string $method = 'POST'): ?array
    {
        $route = RouteFacade::getRoutes()->getByName($name);
        if ($route === null) {
            return null;
        }
        if (! in_array($method, $route->methods(), true)) {
            foreach (RouteFacade::getRoutes()->getRoutes() as $candidate) {
                if ($candidate->uri() === $route->uri() && in_array($method, $candidate->methods(), true)) {
                    $route = $candidate;
                    break;
                }
            }
        }
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return null;
        }
        [$controller, $method] = explode('@', $action, 2);

        return ['controller' => $controller, 'method' => $method];
    }

    /**
     * Every FormRequest under app/Http/Requests, parsed.
     *
     * @return list<ServerForm>
     */
    public function allFormRequests(): array
    {
        $forms = [];
        $finder = Finder::create()->files()->name('*.php')->in($this->basePath.'/app/Http/Requests')->sortByName();
        foreach ($finder as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), $this->basePath.'/app/'));
            if (! class_exists($class) || ! is_subclass_of($class, FormRequest::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $forms[] = $this->forFormRequest($class, $this->firstActionUsing($class));
        }

        return $forms;
    }

    /**
     * Every controller action with an inline validator, parsed.
     *
     * @return list<ServerForm>
     */
    public function allInlineValidators(): array
    {
        $forms = [];
        foreach (array_keys($this->routesByAction) as $action) {
            if (! str_contains($action, '@')) {
                continue;
            }
            [$controller, $method] = explode('@', $action, 2);
            if (! class_exists($controller) || ! method_exists($controller, $method)) {
                continue;
            }
            $inline = $this->inlineRules($controller, $method);
            if ($inline !== null) {
                $forms[] = $inline;
            }
        }
        usort($forms, fn (array $a, array $b) => strcmp($a['source'], $b['source']));

        return $forms;
    }

    /**
     * @param  class-string<FormRequest>  $class
     * @param  array<string, string>  $input
     * @return ServerForm
     */
    private function forFormRequest(string $class, ?string $action, array $input = []): array
    {
        $cacheKey = $class.json_encode($input);
        if (isset($this->formRequestCache[$cacheKey])) {
            return $this->formRequestCache[$cacheKey];
        }

        $reflection = new ReflectionClass($class);
        $file = $this->relative((string) $reflection->getFileName());
        $line = method_exists($class, 'rules') ? (int) (new ReflectionMethod($class, 'rules'))->getStartLine() : (int) $reflection->getStartLine();
        $form = ['source' => $class, 'file' => $file, 'line' => $line, 'validated' => true, 'unparsed' => null, 'fields' => []];

        if (! method_exists($class, 'rules')) {
            return $this->formRequestCache[$cacheKey] = $form;
        }

        DB::beginTransaction();
        try {
            $request = $class::create('/', 'POST', $input);
            $request->setContainer(app())->setRedirector(app('redirect'));
            $route = $action !== null ? $this->boundRoute($action) : null;
            $request->setRouteResolver(fn () => $route);
            // An unsaved account of the request's guard, for rules that ask `$this->user()`.
            $user = str_contains($class, '\\Store\\') ? new StoreUser : new User;
            $request->setUserResolver(fn () => $user);
            /** @var mixed $rules */
            $rules = app()->call([$request, 'rules']);
        } catch (Throwable $exception) {
            $form['unparsed'] = class_basename($exception).': '.Str::limit($exception->getMessage(), 160);

            return $this->formRequestCache[$cacheKey] = $form;
        } finally {
            DB::rollBack();
        }

        if (! is_array($rules)) {
            $form['unparsed'] = 'rules() did not return an array';

            return $this->formRequestCache[$cacheKey] = $form;
        }

        $sources = $this->classSources($class);
        foreach ($rules as $name => $definition) {
            $tokens = $this->runtimeTokens($definition);
            [$keyFile, $keyLine] = $this->locateKey($sources, (string) $name) ?? [$file, $line];
            $form['fields'][(string) $name] = $this->field((string) $name, $tokens, $keyFile, $keyLine);
        }
        $form['fields'] = $this->withConfirmations($form['fields']);

        return $this->formRequestCache[$cacheKey] = $form;
    }

    private function firstActionUsing(string $formRequest): ?string
    {
        foreach ($this->routesByAction as $action => $routes) {
            if (! str_contains($action, '@')) {
                continue;
            }
            [$controller, $method] = explode('@', $action, 2);
            if (! method_exists($controller, $method)) {
                continue;
            }
            foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === $formRequest) {
                    return $action;
                }
            }
        }

        return null;
    }

    /**
     * A copy of the action's route whose parameters are fresh, unsaved
     * instances of the models its controller method type-hints (or "1").
     */
    private function boundRoute(string $action): ?Route
    {
        $route = $this->routesByAction[$action][0] ?? null;
        if ($route === null) {
            return null;
        }
        [$controller, $method] = explode('@', $action, 2);
        $types = [];
        foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $types[$parameter->getName()] = $type->getName();
            }
        }
        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            $type = $types[$name] ?? $types[Str::camel($name)] ?? null;
            $parameters[$name] = $type !== null && is_subclass_of($type, Model::class) ? new $type : '1';
        }
        $bound = clone $route;
        (function (array $parameters): void {
            $this->parameters = $parameters;
        })->call($bound, $parameters);

        return $bound;
    }

    /**
     * @return list<string>
     */
    private function runtimeTokens(mixed $definition): array
    {
        if (is_string($definition)) {
            return array_values(array_filter(array_map(fn (string $token) => Str::lower(trim(Str::before($token, ':'))), explode('|', $definition))));
        }
        if (is_array($definition)) {
            $tokens = [];
            foreach ($definition as $item) {
                array_push($tokens, ...$this->runtimeTokens($item));
            }

            return $tokens;
        }
        if ($definition instanceof RequiredIf) {
            if (is_bool($definition->condition)) {
                return $definition->condition ? ['required'] : [];
            }

            return ['required_if'];
        }
        if ($definition instanceof ConditionalRules) {
            $condition = (fn () => $this->condition)->call($definition);
            $rules = (fn () => $this->rules)->call($definition);
            $default = (fn () => $this->defaultRules)->call($definition);
            if (is_bool($condition)) {
                return $this->runtimeTokens($condition ? $rules : $default);
            }
            $either = [...$this->runtimeTokens($rules), ...$this->runtimeTokens($default)];

            return array_map(fn (string $token) => $token === 'required' ? 'required_when' : $token, $either);
        }
        if ($definition instanceof Closure) {
            return ['closure'];
        }
        if (is_object($definition)) {
            return [Str::lower(class_basename($definition))];
        }

        return [];
    }

    /**
     * @param  list<string>  $tokens
     * @return ServerField
     */
    private function field(string $name, array $tokens, string $file, int $line): array
    {
        $presence = 'optional';
        if (array_intersect($tokens, ['required', 'accepted']) !== []) {
            $presence = 'required';
        } elseif (array_filter($tokens, fn (string $token) => str_starts_with($token, 'required_') || str_starts_with($token, 'accepted_') || str_starts_with($token, 'expr:')) !== []) {
            $presence = 'conditional';
        }

        return ['name' => $name, 'presence' => $presence, 'rules' => implode('|', array_unique($tokens)), 'file' => $file, 'line' => $line, 'derived' => false];
    }

    /**
     * @param  array<string, ServerField>  $fields
     * @return array<string, ServerField>
     */
    private function withConfirmations(array $fields): array
    {
        foreach ($fields as $name => $field) {
            $confirmation = $name.'_confirmation';
            if (in_array('confirmed', explode('|', $field['rules']), true) && ! isset($fields[$confirmation])) {
                $fields[$confirmation] = [
                    'name' => $confirmation,
                    'presence' => $field['presence'] === 'required' ? 'required' : 'conditional',
                    'rules' => "same as {$name} (confirmed)",
                    'file' => $field['file'],
                    'line' => $field['line'],
                    'derived' => true,
                ];
            }
        }

        return $fields;
    }

    /**
     * The files whose source may hold a FormRequest's rule keys: the class,
     * its parents and every trait they use.
     *
     * @param  class-string  $className
     * @return list<string>
     */
    private function classSources(string $className): array
    {
        $files = [];
        for ($class = new ReflectionClass($className); $class !== false && str_starts_with($class->getName(), 'App\\'); $class = $class->getParentClass()) {
            $files[] = (string) $class->getFileName();
            foreach (class_uses_recursive($class->getName()) as $trait) {
                if (trait_exists($trait)) {
                    $files[] = (string) (new ReflectionClass($trait))->getFileName();
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param  list<string>  $files
     * @return array{0: string, 1: int}|null
     */
    private function locateKey(array $files, string $key): ?array
    {
        $pattern = '/[\'"]'.preg_quote($key, '/').'[\'"]\s*=>/';
        foreach ($files as $file) {
            foreach (file($file) ?: [] as $index => $text) {
                if (preg_match($pattern, $text) === 1) {
                    return [$this->relative($file), $index + 1];
                }
            }
        }

        return null;
    }

    /**
     * @param  class-string  $controller
     * @return ServerForm|null
     */
    private function inlineRules(string $controller, string $method): ?array
    {
        $file = (string) (new ReflectionClass($controller))->getFileName();
        $methods = $this->methods($file);
        $calls = $this->validationCalls($methods, $method, 0);
        if ($calls === []) {
            return null;
        }

        $form = [
            'source' => $controller.'@'.$method.' (inline)',
            'file' => $this->relative($file),
            'line' => $calls[0]->getStartLine(),
            'validated' => true,
            'unparsed' => null,
            'fields' => [],
        ];
        foreach ($calls as $call) {
            $rules = $this->rulesArgument($call);
            if (! $rules instanceof Expr\Array_) {
                $form['unparsed'] ??= 'rules are not a literal array at line '.$call->getStartLine();

                continue;
            }
            foreach ($rules->items as $item) {
                if (! $item->key instanceof String_) {
                    $form['unparsed'] ??= 'a rule key is not a string literal at line '.$item->getStartLine();

                    continue;
                }
                $form['fields'][$item->key->value] = $this->field($item->key->value, $this->sourceTokens($item->value), $form['file'], $item->getStartLine());
            }
        }
        $form['fields'] = $this->withConfirmations($form['fields']);

        return $form;
    }

    /**
     * @return array<string, ClassMethod>
     */
    private function methods(string $file): array
    {
        $this->astCache[$file] ??= (new ParserFactory)->createForHostVersion()->parse((string) file_get_contents($file)) ?? [];
        $methods = [];
        foreach ((new NodeFinder)->findInstanceOf($this->astCache[$file], ClassMethod::class) as $node) {
            $methods[$node->name->toString()] = $node;
        }

        return $methods;
    }

    /**
     * @param  array<string, ClassMethod>  $methods
     * @return list<Expr\MethodCall|Expr\StaticCall>
     */
    private function validationCalls(array $methods, string $method, int $depth): array
    {
        if (! isset($methods[$method]) || $depth > 2) {
            return [];
        }
        $calls = [];
        $finder = new NodeFinder;
        foreach ($finder->find($methods[$method]->stmts ?? [], fn (Node $node) => $node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall) as $node) {
            /** @var Expr\MethodCall|Expr\StaticCall $node */
            $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            if ($node instanceof Expr\StaticCall && $name === 'make' && $node->class instanceof Node\Name && $node->class->getLast() === 'Validator') {
                $calls[] = $node;
            } elseif ($node instanceof Expr\MethodCall && in_array($name, ['validate', 'validateWithBag'], true) && $node->args !== []) {
                $calls[] = $node;
            } elseif ($node instanceof Expr\MethodCall && $node->var instanceof Expr\Variable && $node->var->name === 'this' && $name !== null && $name !== $method && isset($methods[$name])) {
                array_push($calls, ...$this->validationCalls($methods, $name, $depth + 1));
            }
        }

        return $calls;
    }

    private function rulesArgument(Expr\MethodCall|Expr\StaticCall $call): ?Expr
    {
        $arguments = array_values(array_filter($call->args, fn ($argument) => $argument instanceof Node\Arg));
        $name = $call->name instanceof Node\Identifier ? $call->name->toString() : '';
        $isThisValidate = $call instanceof Expr\MethodCall && $call->var instanceof Expr\Variable && $call->var->name === 'this';
        $index = match (true) {
            $call instanceof Expr\StaticCall => 1,
            $name === 'validateWithBag' => $isThisValidate ? 2 : 1,
            $isThisValidate => 1,
            default => 0,
        };

        return $arguments[$index]->value ?? null;
    }

    /**
     * @return list<string>
     */
    private function sourceTokens(Expr $value): array
    {
        if ($value instanceof String_) {
            return $this->runtimeTokens($value->value);
        }
        if ($value instanceof Expr\Array_) {
            $tokens = [];
            foreach ($value->items as $item) {
                array_push($tokens, ...$this->sourceTokens($item->value));
            }

            return $tokens;
        }
        if ($value instanceof Expr\StaticCall && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            $call = $value->class->getLast().'::'.$value->name->toString();

            return match ($call) {
                'Rule::requiredIf' => ['required_if'],
                'Rule::when' => ['required_when'],
                default => [Str::lower($value->name->toString())],
            };
        }
        if ($value instanceof Expr\Ternary && $value->if !== null) {
            $either = [...$this->sourceTokens($value->if), ...$this->sourceTokens($value->else)];

            return array_map(fn (string $token) => $token === 'required' ? 'required_when' : $token, $either);
        }
        if ($value instanceof Expr\New_ && $value->class instanceof Node\Name) {
            return [Str::lower($value->class->getLast())];
        }

        return ['expr:'.(new Standard)->prettyPrintExpr($value)];
    }

    /**
     * @return ServerForm
     */
    private function unparsedForm(string $source, string $reason): array
    {
        return ['source' => $source, 'file' => '', 'line' => 0, 'validated' => false, 'unparsed' => $reason, 'fields' => []];
    }

    private function relative(string $file): string
    {
        return ltrim(Str::after($file, $this->basePath), '/');
    }
}
