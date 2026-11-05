import { i18n } from '@/lib/i18n';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';

/**
 * The browser-side copy of a form's Laravel rules (.ai/rules/client-side-validation.md).
 *
 * `rules` maps a field (dot notation, `*` for any row) to Laravel rule
 * tokens, in the server's order (`required`, `max:200`, `lte:max_qty`, ...).
 * `named` adds the copies of closures the server runs, resolved from
 * lib/formValidationNamedRules.ts. A parity test compares each copy with the
 * FormRequest; the server stays the authority, and the copy may be more
 * lenient than it, never stricter.
 */
export interface ValidationSchema {
    rules: Record<string, string[]>;
    named?: Record<string, NamedRule[]>;
}

/**
 * A server closure's copy: the fields it reads besides its own (to check it
 * again when they change) and the check, given the field's value and a
 * reader for the others.
 */
export interface NamedRule {
    dependsOn: string[];
    validate(
        value: FieldValue,
        get: (field: string) => FieldValue | undefined,
    ): RuleFailure | null;
}

/** A field's value as the server sees it: trimmed, blank as null, rows as lists. */
export type FieldValue = string | null | FieldValue[];

/**
 * The form's controls as the server would receive them: `values` holds each
 * posted leaf (`variants.0.price`), `children` the posted rows under a key
 * (`product_barcodes` -> `product_barcodes.0`, ...), `known` every key a
 * control names (posted or not, an unticked radio group included) and
 * `elements` each control's key.
 */
export interface FormSnapshot {
    values: Map<string, string | null>;
    children: Map<string, string[]>;
    known: Set<string>;
    elements: Map<Element, string>;
}

/** A failed rule: its message key under `validation.messages` and replacements. */
export interface RuleFailure {
    message: string;
    params?: Record<string, string>;
}

/** The tokens this engine checks; any other token is server-only. */
export const supportedTokens = [
    'bail',
    'sometimes',
    'nullable',
    'required',
    'required_if',
    'required_with',
    'string',
    'integer',
    'numeric',
    'boolean',
    'array',
    'decimal',
    'min',
    'max',
    'in',
    'url',
    'regex',
    'distinct',
    'lt',
    'lte',
    'gt',
    'gte',
] as const;

const flagTokens = new Set(['bail', 'sometimes', 'nullable']);
const implicitTokens = new Set(['required', 'required_if', 'required_with']);
const crossFieldTokens = new Set(['required_with', 'lt', 'lte', 'gt', 'gte']);
const numericTokens = new Set(['numeric', 'integer', 'decimal']);
const skippedTypes = new Set(['submit', 'button', 'reset', 'image', 'file']);

type ValidationLines = {
    messages: Record<string, string | Record<string, string>>;
    attributes: Record<string, string>;
};

/** `in:a,b` -> ['in', ['a', 'b']]; `regex` keeps its pattern whole. */
export function parseToken(token: string): [string, string[]] {
    const at = token.indexOf(':');
    if (at === -1) {
        return [token, []];
    }
    const name = token.slice(0, at);
    const rest = token.slice(at + 1);

    return [name, name === 'regex' ? [rest] : rest.split(',')];
}

/** `variants[0][price]` -> ['variants', '0', 'price']; `tags[]` -> ['tags', '']. */
function nameSegments(name: string): string[] {
    const base = name.match(/^[^[]+/)?.[0] ?? name;
    const brackets = [...name.slice(base.length).matchAll(/\[([^\]]*)\]/g)];

    return [base, ...brackets.map((match) => match[1])];
}

function normalise(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * Reads the form as PHP would parse the posted fields: `[]` takes the next
 * index under its prefix, a repeated plain name keeps its last value (a
 * hidden `0` before a ticked checkbox), and disabled controls post nothing.
 */
export function readForm(form: HTMLFormElement): FormSnapshot {
    const snapshot: FormSnapshot = {
        values: new Map(),
        children: new Map(),
        known: new Set(),
        elements: new Map(),
    };
    const counters = new Map<string, number>();

    for (const element of Array.from(form.elements)) {
        if (
            !(
                element instanceof HTMLInputElement ||
                element instanceof HTMLSelectElement ||
                element instanceof HTMLTextAreaElement
            ) ||
            element.name === '' ||
            element.disabled ||
            skippedTypes.has(element.type)
        ) {
            continue;
        }
        const segments = nameSegments(element.name);
        const autoAt = segments.indexOf('', 1);
        const fixed = segments.slice(0, autoAt === -1 ? undefined : autoAt);
        let posted: string[];
        if (
            element instanceof HTMLInputElement &&
            (element.type === 'checkbox' || element.type === 'radio')
        ) {
            posted = element.checked ? [element.value] : [];
        } else if (element instanceof HTMLSelectElement && element.multiple) {
            posted = Array.from(element.selectedOptions).map((o) => o.value);
        } else {
            posted = [element.value];
        }
        for (let depth = 1; depth <= fixed.length; depth++) {
            snapshot.known.add(fixed.slice(0, depth).join('.'));
        }
        if (!snapshot.elements.has(element)) {
            snapshot.elements.set(element, fixed.join('.'));
        }
        for (const value of posted) {
            const resolved = segments.map((segment, index) => {
                if (index === 0 || segment !== '') {
                    return segment;
                }
                const prefix = segments.slice(0, index).join('.');
                const next = counters.get(prefix) ?? 0;
                counters.set(prefix, next + 1);

                return String(next);
            });
            const key = resolved.join('.');
            snapshot.values.set(key, normalise(value));
            snapshot.elements.set(element, key);
            for (let depth = 1; depth < resolved.length; depth++) {
                const parent = resolved.slice(0, depth).join('.');
                const child = resolved.slice(0, depth + 1).join('.');
                snapshot.known.add(parent);
                const list = snapshot.children.get(parent) ?? [];
                if (!list.includes(child)) {
                    list.push(child);
                }
                snapshot.children.set(parent, list);
            }
            snapshot.known.add(key);
        }
    }

    return snapshot;
}

/** The posted value, a list for a key with rows, or undefined when absent. */
export function valueOf(
    snapshot: FormSnapshot,
    key: string,
): FieldValue | undefined {
    if (snapshot.values.has(key)) {
        return snapshot.values.get(key) ?? null;
    }
    const rows = snapshot.children.get(key);

    return rows === undefined
        ? undefined
        : rows.map((row) => valueOf(snapshot, row) ?? null);
}

function patternRegex(pattern: string): RegExp {
    const escaped = pattern
        .split('*')
        .map((part) => part.replace(/[.+?^${}()|[\]\\]/g, '\\$&'));

    return new RegExp(`^${escaped.join('[^.]+')}$`);
}

/** The rule key (`variants.*.price`) a concrete key (`variants.3.price`) falls under. */
export function patternFor(
    schema: ValidationSchema,
    key: string,
): string | undefined {
    if (key in schema.rules) {
        return key;
    }

    return Object.keys(schema.rules).find(
        (pattern) => pattern.includes('*') && patternRegex(pattern).test(key),
    );
}

/** The concrete keys a rule key applies to: those a control in the form names. */
export function keysFor(snapshot: FormSnapshot, pattern: string): string[] {
    if (!pattern.includes('*')) {
        return snapshot.known.has(pattern) ? [pattern] : [];
    }
    const regex = patternRegex(pattern);

    return [...snapshot.known].filter((key) => regex.test(key));
}

/** PHP's is_numeric() for a trimmed string. */
export function isNumeric(value: unknown): value is string {
    return (
        typeof value === 'string' &&
        /^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/.test(value)
    );
}

function filled(value: FieldValue | undefined): boolean {
    return (
        value !== undefined &&
        value !== null &&
        !(Array.isArray(value) && value.length === 0)
    );
}

/** PHP's loose `==` between two posted strings ("1" == "01"). */
function looselyEqual(a: string, b: string): boolean {
    return isNumeric(a) && isNumeric(b) ? Number(a) === Number(b) : a === b;
}

function sizeType(tokens: string[]): 'numeric' | 'array' | 'string' {
    const names = tokens.map((token) => parseToken(token)[0]);
    if (names.some((name) => numericTokens.has(name))) {
        return 'numeric';
    }

    return names.includes('array') ? 'array' : 'string';
}

/** Laravel's getSize(): the number, the row count or the character count. */
function sizeOf(value: FieldValue, type: string): number {
    if (type === 'numeric' && isNumeric(value)) {
        return Number(value);
    }
    if (Array.isArray(value)) {
        return value.length;
    }

    return [...(value ?? '')].length;
}

function isUrl(value: string, protocols: string[]): boolean {
    if (/\s/.test(value)) {
        return false;
    }
    try {
        const url = new URL(value);

        return (
            url.hostname !== '' &&
            (protocols.length === 0 ||
                protocols.includes(url.protocol.replace(/:$/, '')))
        );
    } catch {
        return false;
    }
}

function phpRegex(pattern: string): RegExp | null {
    const match = pattern.match(/^(.)(.*)\1([a-zA-Z]*)$/s);
    if (!match) {
        return null;
    }
    try {
        return new RegExp(match[2], match[3].replace(/[^imsu]/g, ''));
    } catch {
        return null;
    }
}

/** Laravel's decimal rule: numeric, with min (to max) decimal places. */
function hasDecimals(value: string, args: string[]): boolean {
    const match = value.match(/^[+-]?\d*\.?(\d*)$/);
    if (!isNumeric(value) || !match) {
        return false;
    }
    const places = match[1].length;
    const min = Number(args[0]);
    const max = args[1] === undefined ? min : Number(args[1]);

    return places >= min && places <= max;
}

/** One rule against one value; null when it passes. */
function check(
    name: string,
    args: string[],
    key: string,
    pattern: string,
    tokens: string[],
    value: FieldValue | undefined,
    snapshot: FormSnapshot,
): RuleFailure | null {
    const fail = (
        message: string,
        params: Record<string, string> = {},
    ): RuleFailure => ({ message, params });
    const type = sizeType(tokens);
    switch (name) {
        case 'required':
            return filled(value) ? null : fail('required');
        case 'required_if': {
            const other = valueOf(snapshot, args[0]);
            const applies =
                typeof other === 'string' && args.slice(1).includes(other);

            return !applies || filled(value)
                ? null
                : fail('required_if', {
                      other: attributeName(args[0]),
                      value: String(other),
                  });
        }
        case 'required_with': {
            const applies = args.some((field) =>
                filled(valueOf(snapshot, field)),
            );

            return !applies || filled(value)
                ? null
                : fail('required_with', {
                      values: args.map(attributeName).join(' / '),
                  });
        }
        case 'string':
            return typeof value === 'string' ? null : fail('string');
        case 'integer':
            return typeof value === 'string' &&
                /^[+-]?(0|[1-9]\d*)$/.test(value)
                ? null
                : fail('integer');
        case 'numeric':
            return isNumeric(value) ? null : fail('numeric');
        case 'boolean':
            return value === '0' || value === '1' ? null : fail('boolean');
        case 'array':
            return Array.isArray(value) ? null : fail('array');
        case 'decimal':
            return typeof value === 'string' && hasDecimals(value, args)
                ? null
                : fail('decimal', {
                      decimal: args.length > 1 ? args.join('-') : args[0],
                  });
        case 'min':
            return sizeOf(value ?? null, type) >= Number(args[0])
                ? null
                : fail(`min.${type}`, { min: args[0] });
        case 'max':
            return sizeOf(value ?? null, type) <= Number(args[0])
                ? null
                : fail(`max.${type}`, { max: args[0] });
        case 'in':
            return typeof value === 'string' && args.includes(value)
                ? null
                : fail('in');
        case 'url':
            return typeof value === 'string' && isUrl(value, args)
                ? null
                : fail('url');
        case 'regex': {
            const regex = phpRegex(args[0]);

            return regex === null ||
                (typeof value === 'string' && regex.test(value))
                ? null
                : fail('regex');
        }
        case 'distinct': {
            if (typeof value !== 'string' || !pattern.includes('*')) {
                return null;
            }
            const duplicate = keysFor(snapshot, pattern).some((other) => {
                const otherValue = snapshot.values.get(other);

                return (
                    other !== key &&
                    typeof otherValue === 'string' &&
                    looselyEqual(value, otherValue)
                );
            });

            return duplicate ? fail('distinct') : null;
        }
        case 'lt':
        case 'lte':
        case 'gt':
        case 'gte': {
            // Lenient where Laravel compares types or sizes: only two numbers
            // are compared, so the client never refuses what the server accepts.
            const bound = isNumeric(args[0])
                ? args[0]
                : valueOf(snapshot, args[0]);
            if (type !== 'numeric' || !isNumeric(value) || !isNumeric(bound)) {
                return null;
            }
            const [a, b] = [Number(value), Number(bound)];
            const passes = {
                lt: a < b,
                lte: a <= b,
                gt: a > b,
                gte: a >= b,
            }[name];

            return passes ? null : fail(`${name}.numeric`, { value: bound });
        }
        default:
            return null;
    }
}

function lines(): ValidationLines {
    const locale = i18n.global.locale.value === 'ar' ? ar : en;

    return locale.validation as ValidationLines;
}

/**
 * Laravel's getDisplayableAttribute(): the translated name of the key or of
 * its rule key; a row key without one stays as it is (`variants.0.image`),
 * any other loses its underscores.
 */
export function attributeName(key: string, pattern?: string): string {
    const attributes = lines().attributes;
    const rule = pattern ?? key;

    return (
        attributes[key] ??
        attributes[rule] ??
        (rule.includes('*') ? key : key.replaceAll('_', ' '))
    );
}

function lineFor(messageKey: string): string {
    const [group, type] = messageKey.split('.');
    const line = lines().messages[group] ?? en.validation.messages.required;

    return typeof line === 'string'
        ? line
        : (line[type] ?? en.validation.messages.required);
}

function ucfirst(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

/** Laravel's placeholder replacement: `:key`, `:Key` and `:KEY`, longest first. */
export function formatMessage(
    line: string,
    params: Record<string, string>,
): string {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (text, name) =>
                text
                    .replaceAll(
                        `:${name.toUpperCase()}`,
                        params[name].toUpperCase(),
                    )
                    .replaceAll(`:${ucfirst(name)}`, ucfirst(params[name]))
                    .replaceAll(`:${name}`, params[name]),
            line,
        );
}

/**
 * The first message the server would give for this key, or undefined.
 * Mirrors Laravel's order: an absent field runs only the required rules,
 * a null one marked nullable skips the others, and the first failure wins.
 */
export function validateKey(
    snapshot: FormSnapshot,
    schema: ValidationSchema,
    key: string,
): string | undefined {
    const pattern = patternFor(schema, key);
    if (pattern === undefined) {
        return undefined;
    }
    const tokens = schema.rules[pattern];
    const names = tokens.map((token) => parseToken(token)[0]);
    const value = valueOf(snapshot, key);
    const present = value !== undefined;
    if (names.includes('sometimes') && !present) {
        return undefined;
    }
    const skipsOptional =
        !present || (value === null && names.includes('nullable'));
    const failure =
        tokens.reduce<RuleFailure | null>((found, token) => {
            const [name, args] = parseToken(token);
            if (found || flagTokens.has(name)) {
                return found;
            }
            if (!implicitTokens.has(name) && skipsOptional) {
                return null;
            }

            return check(name, args, key, pattern, tokens, value, snapshot);
        }, null) ??
        (skipsOptional
            ? null
            : (schema.named?.[pattern] ?? []).reduce<RuleFailure | null>(
                  (found, rule) =>
                      found ??
                      rule.validate(value ?? null, (field) =>
                          valueOf(snapshot, field),
                      ),
                  null,
              ));
    if (failure === null) {
        return undefined;
    }

    return formatMessage(lineFor(failure.message), {
        attribute: attributeName(key, pattern),
        ...failure.params,
    });
}

/** Every rule key against every control the form has for it. */
export function validateForm(
    snapshot: FormSnapshot,
    schema: ValidationSchema,
): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const pattern of Object.keys(schema.rules)) {
        for (const key of keysFor(snapshot, pattern)) {
            const message = validateKey(snapshot, schema, key);
            if (message !== undefined) {
                errors[key] = message;
            }
        }
    }

    return errors;
}

/**
 * The rule keys whose result depends on `field`: those naming it in
 * `required_if`, `required_with` or a comparison, and named rules listing it.
 */
export function dependentPatterns(
    schema: ValidationSchema,
    field: string,
): string[] {
    return Object.keys(schema.rules).filter(
        (pattern) =>
            schema.rules[pattern].some((token) => {
                const [name, args] = parseToken(token);

                return (
                    (name === 'required_if' && args[0] === field) ||
                    (crossFieldTokens.has(name) && args.includes(field))
                );
            }) ||
            (schema.named?.[pattern] ?? []).some((rule) =>
                rule.dependsOn.includes(field),
            ),
    );
}
