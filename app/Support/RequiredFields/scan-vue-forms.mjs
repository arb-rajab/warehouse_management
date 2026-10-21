/**
 * The Vue half of `php artisan forms:audit-required` (App\Support\RequiredFields\VueFormScanner
 * runs it with node and reads its JSON from stdout). Read-only.
 *
 *   node app/Support/RequiredFields/scan-vue-forms.mjs [repo root]
 *
 * For every SFC under resources/js/pages and resources/js/components it finds
 * the elements whose `:action` (or `v-bind="x.form()"`) is a Wayfinder call it
 * can resolve through the file's imports (`@/actions/...Controller` or
 * `@/routes/...`), then collects the form's native controls: the element's
 * slot content in the file's own scope, and every local component it renders,
 * followed into that component's template with the props the parent passed
 * (literals, `t('key')` labels resolved from en.json, and the props' declared
 * defaults; anything else is "unknown"). For each control it reports the
 * name, whether it is `required` (true, false or "dynamic"), whether its label
 * shows <RequiredMark> and the label text. The shared field components whose
 * control is not a plain named input (StoreUploadField, StoreRichTextField,
 * ProductSelect) are read from their props instead: RequiredFieldComponents.test.ts
 * guarantees they render the mark exactly when `required` is set.
 *
 * Limits: expressions are evaluated only for literals, props, `!`, `&&`,
 * `||`, `??`, `===`/`!==`, ternaries, template literals and `t()`; a name
 * built from runtime data is reported as unresolved, a form whose action is a
 * computed value as unresolved (class F). Forms submitted from script
 * (`useForm`, `router.post`) are not found.
 */
import fs from 'node:fs';
import path from 'node:path';
import { parseExpression, parse as parseScript } from '@babel/parser';
import { parse as parseSfc } from '@vue/compiler-sfc';

const root = path.resolve(process.argv[2] ?? process.cwd());
const jsRoot = path.join(root, 'resources/js');
const messages = JSON.parse(
    fs.readFileSync(path.join(jsRoot, 'locales/en.json'), 'utf8'),
);

const UNKNOWN = Symbol('unknown');
const NODE_ELEMENT = 1;
const NODE_TEXT = 2;
const NODE_INTERPOLATION = 5;
const NODE_ATTRIBUTE = 6;
const NODE_DIRECTIVE = 7;
const MAX_DEPTH = 8;

/**
 * Field components whose control is not a plain named input: their usage is
 * the control. `name` is the prop holding the posted name.
 */
const COMPOSITE_FIELDS = {
    'StoreUploadField.vue': { name: ['name'], hidden: false },
    'StoreRichTextField.vue': { name: ['name'], hidden: false },
    'ProductSelect.vue': { name: [], hidden: false },
};

/**
 * Shared single-field components: a control found inside one is reported at
 * the component's usage site, which is where a reviewer fixes it.
 */
const FIELD_COMPONENTS = new Set([
    'FormField.vue',
    'StoreSelectField.vue',
    'StoreBooleanField.vue',
    'FilterNumberField.vue',
    'FilterDateField.vue',
    'FilterSelect.vue',
    'FilterCheckbox.vue',
    ...Object.keys(COMPOSITE_FIELDS),
]);

const NATIVE_CONTROLS = new Set(['input', 'select', 'textarea']);

function rel(file) {
    return path.relative(root, file).split(path.sep).join('/');
}

function camelize(name) {
    return name.replace(/-(\w)/g, (_, c) => c.toUpperCase());
}

function hyphenate(name) {
    return name.replace(/\B([A-Z])/g, '-$1').toLowerCase();
}

function lookupMessage(key) {
    const value = key
        .split('.')
        .reduce(
            (node, part) =>
                node && typeof node === 'object' ? node[part] : undefined,
            messages,
        );

    return typeof value === 'string' ? value : UNKNOWN;
}

/* ---------------------------------------------------------------- SFC cache */

const sfcCache = new Map();

function unwrapTs(node) {
    while (
        node &&
        (node.type === 'TSAsExpression' ||
            node.type === 'TSSatisfiesExpression' ||
            node.type === 'TSNonNullExpression')
    ) {
        node = node.expression;
    }

    return node;
}

function literalValue(node) {
    node = unwrapTs(node);

    if (!node) {
        return UNKNOWN;
    }

    switch (node.type) {
        case 'StringLiteral':
        case 'NumericLiteral':
        case 'BooleanLiteral':
            return node.value;
        case 'NullLiteral':
            return null;
        case 'TemplateLiteral':
            return node.expressions.length === 0
                ? node.quasis[0].value.cooked
                : UNKNOWN;
        case 'ArrayExpression': {
            const values = node.elements.map(literalValue);

            return values.includes(UNKNOWN) ? UNKNOWN : values;
        }
        case 'ArrowFunctionExpression':
            return literalValue(node.body);
        default:
            return UNKNOWN;
    }
}

function readProps(callNode, info) {
    let defaults = null;
    let call = callNode;

    if (call.callee.name === 'withDefaults') {
        defaults = call.arguments[1];
        call = call.arguments[0];
    }

    if (call?.type !== 'CallExpression' || call.callee.name !== 'defineProps') {
        return;
    }

    const typeLiteral = call.typeParameters?.params?.[0];

    if (typeLiteral?.type === 'TSTypeLiteral') {
        for (const member of typeLiteral.members) {
            if (member.type !== 'TSPropertySignature') {
                continue;
            }

            const name = member.key.name ?? member.key.value;
            const type = member.typeAnnotation?.typeAnnotation;
            info.props.set(name, {
                boolean:
                    type?.type === 'TSBooleanKeyword' ||
                    (type?.type === 'TSUnionType' &&
                        type.types.some((t) => t.type === 'TSBooleanKeyword')),
            });
        }
    }

    const runtime = call.arguments[0];

    if (runtime?.type === 'ObjectExpression') {
        for (const property of runtime.properties) {
            const name = property.key?.name ?? property.key?.value;
            const value = property.value;
            let boolean =
                value?.type === 'Identifier' && value.name === 'Boolean';
            let fallback = UNKNOWN;

            if (value?.type === 'ObjectExpression') {
                for (const option of value.properties) {
                    if (option.key?.name === 'type') {
                        boolean =
                            option.value.type === 'Identifier' &&
                            option.value.name === 'Boolean';
                    }

                    if (option.key?.name === 'default') {
                        fallback = literalValue(option.value);
                    }
                }
            }

            info.props.set(name, { boolean });

            if (fallback !== UNKNOWN) {
                info.defaults.set(name, fallback);
            }
        }
    }

    if (defaults?.type === 'ObjectExpression') {
        for (const property of defaults.properties) {
            const name = property.key?.name ?? property.key?.value;
            const value = literalValue(property.value);

            if (name && value !== UNKNOWN) {
                info.defaults.set(name, value);
            }
        }
    }
}

function loadSfc(file) {
    if (sfcCache.has(file)) {
        return sfcCache.get(file);
    }

    const source = fs.readFileSync(file, 'utf8');
    const { descriptor } = parseSfc(source, { filename: file });
    const info = {
        file,
        template: descriptor.template?.ast ?? null,
        imports: new Map(),
        constants: new Map(),
        props: new Map(),
        defaults: new Map(),
        inheritAttrs: true,
        declaresActionProp: false,
    };

    for (const block of [descriptor.script, descriptor.scriptSetup]) {
        if (!block) {
            continue;
        }

        let program;

        try {
            program = parseScript(block.content, {
                sourceType: 'module',
                plugins: ['typescript'],
            }).program;
        } catch {
            continue;
        }

        for (const statement of program.body) {
            if (statement.type === 'ImportDeclaration') {
                const from = statement.source.value;

                for (const specifier of statement.specifiers) {
                    info.imports.set(specifier.local.name, {
                        from,
                        imported:
                            specifier.type === 'ImportSpecifier'
                                ? (specifier.imported.name ??
                                  specifier.imported.value)
                                : null,
                    });
                }

                continue;
            }

            const declarations =
                statement.type === 'VariableDeclaration'
                    ? statement.declarations
                    : statement.type === 'ExportNamedDeclaration' &&
                        statement.declaration?.type === 'VariableDeclaration'
                      ? statement.declaration.declarations
                      : [];

            for (const declaration of declarations) {
                const init = unwrapTs(declaration.init);

                if (init?.type === 'CallExpression') {
                    readProps(init, info);
                }

                if (declaration.id.type === 'Identifier') {
                    const value = literalValue(init);

                    if (value !== UNKNOWN) {
                        info.constants.set(declaration.id.name, value);
                    }
                }
            }

            if (
                statement.type === 'ExpressionStatement' &&
                statement.expression.type === 'CallExpression'
            ) {
                const call = statement.expression;
                readProps(call, info);

                if (
                    call.callee.name === 'defineOptions' &&
                    call.arguments[0]?.type === 'ObjectExpression'
                ) {
                    for (const property of call.arguments[0].properties) {
                        if (property.key?.name === 'inheritAttrs') {
                            info.inheritAttrs =
                                literalValue(property.value) !== false;
                        }
                    }
                }
            }
        }
    }

    info.declaresActionProp = info.props.has('action');
    sfcCache.set(file, info);

    return info;
}

function resolveComponentFile(info, tag) {
    const imported = info.imports.get(tag);

    if (!imported || !imported.from.endsWith('.vue')) {
        return null;
    }

    const from = imported.from;
    const file = from.startsWith('@/')
        ? path.join(jsRoot, from.slice(2))
        : path.resolve(path.dirname(info.file), from);

    return fs.existsSync(file) ? file : null;
}

/* -------------------------------------------------------------- evaluation */

function evaluate(code, scope) {
    if (code === undefined || code === null) {
        return UNKNOWN;
    }

    let node;

    try {
        node = parseExpression(code, { plugins: ['typescript'] });
    } catch {
        return UNKNOWN;
    }

    return evaluateNode(node, scope);
}

function evaluateNode(node, scope) {
    node = unwrapTs(node);

    switch (node.type) {
        case 'StringLiteral':
        case 'NumericLiteral':
        case 'BooleanLiteral':
            return node.value;
        case 'NullLiteral':
            return null;
        case 'Identifier':
            if (node.name === 'undefined') {
                return undefined;
            }

            return scope.has(node.name) ? scope.get(node.name) : UNKNOWN;
        case 'TemplateLiteral': {
            let out = '';

            for (let i = 0; i < node.quasis.length; i++) {
                out += node.quasis[i].value.cooked;

                if (i < node.expressions.length) {
                    const value = evaluateNode(node.expressions[i], scope);

                    if (value === UNKNOWN || typeof value === 'object') {
                        return UNKNOWN;
                    }

                    out += String(value);
                }
            }

            return out;
        }
        case 'UnaryExpression': {
            const value = evaluateNode(node.argument, scope);

            if (node.operator === '!') {
                return value === UNKNOWN ? UNKNOWN : !value;
            }

            return UNKNOWN;
        }
        case 'LogicalExpression': {
            const left = evaluateNode(node.left, scope);

            if (node.operator === '&&') {
                if (left !== UNKNOWN && !left) {
                    return left;
                }

                const right = evaluateNode(node.right, scope);

                return left === UNKNOWN
                    ? right !== UNKNOWN && !right
                        ? right
                        : UNKNOWN
                    : right;
            }

            if (node.operator === '||') {
                if (left !== UNKNOWN && left) {
                    return left;
                }

                const right = evaluateNode(node.right, scope);

                return left === UNKNOWN
                    ? right !== UNKNOWN && right
                        ? right
                        : UNKNOWN
                    : right;
            }

            if (node.operator === '??') {
                if (left === UNKNOWN) {
                    return UNKNOWN;
                }

                return left ?? evaluateNode(node.right, scope);
            }

            return UNKNOWN;
        }
        case 'BinaryExpression': {
            const left = evaluateNode(node.left, scope);
            const right = evaluateNode(node.right, scope);

            if (left === UNKNOWN || right === UNKNOWN) {
                return UNKNOWN;
            }

            switch (node.operator) {
                case '===':
                    return left === right;
                case '!==':
                    return left !== right;
                case '==':
                    return left == right;
                case '!=':
                    return left != right;
                case '+':
                    return left + right;
                default:
                    return UNKNOWN;
            }
        }
        case 'ConditionalExpression': {
            const test = evaluateNode(node.test, scope);

            if (test === UNKNOWN) {
                const consequent = evaluateNode(node.consequent, scope);
                const alternate = evaluateNode(node.alternate, scope);

                return consequent === alternate ? consequent : UNKNOWN;
            }

            return evaluateNode(test ? node.consequent : node.alternate, scope);
        }
        case 'CallExpression': {
            const callee = node.callee;

            if (callee.type === 'Identifier' && callee.name === 't') {
                const key = evaluateNode(node.arguments[0], scope);

                return typeof key === 'string' ? lookupMessage(key) : UNKNOWN;
            }

            if (
                callee.type === 'MemberExpression' &&
                callee.object.type === 'ArrayExpression' &&
                callee.property.name === 'includes'
            ) {
                const list = literalValue(callee.object);
                const value = evaluateNode(node.arguments[0], scope);

                return list === UNKNOWN || value === UNKNOWN
                    ? UNKNOWN
                    : list.includes(value);
            }

            return UNKNOWN;
        }
        default:
            return UNKNOWN;
    }
}

/** What `required` renders as on a native element. */
function presence(value) {
    if (value === UNKNOWN) {
        return 'dynamic';
    }

    return (
        value === '' ||
        (value !== false &&
            value !== null &&
            value !== undefined &&
            value !== 0)
    );
}

/* ------------------------------------------------------- form action lookup */

const WAYFINDER_MEMBERS = new Set([
    'url',
    'form',
    'get',
    'head',
    'post',
    'put',
    'patch',
    'delete',
]);

function importTarget(local, member, info) {
    const imported = info.imports.get(local);

    if (!imported) {
        return null;
    }

    const name =
        imported.imported ??
        (member && !WAYFINDER_MEMBERS.has(member) ? member : null);

    if (!name) {
        return null;
    }

    const actionPrefix = '@/actions/';
    const routePrefix = '@/routes/';

    if (imported.from.startsWith(actionPrefix)) {
        return {
            controller: imported.from
                .slice(actionPrefix.length)
                .split('/')
                .join('\\'),
            method: name,
        };
    }

    if (imported.from.startsWith(routePrefix)) {
        const base = imported.from
            .slice(routePrefix.length)
            .split('/')
            .filter((part) => part !== 'index');

        return { route: [...base, name].join('.') };
    }

    return null;
}

function calleeTarget(callee, info) {
    callee = unwrapTs(callee);

    if (callee.type === 'Identifier') {
        return importTarget(callee.name, null, info);
    }

    if (
        callee.type === 'MemberExpression' ||
        callee.type === 'OptionalMemberExpression'
    ) {
        const property = callee.computed
            ? callee.property.type === 'StringLiteral'
                ? callee.property.value
                : null
            : callee.property.name;
        const object = unwrapTs(callee.object);

        if (object.type === 'Identifier') {
            // `store.url()`, `update_note['/temp/...'](visit)` or `Controller.store()`.
            return importTarget(
                object.name,
                callee.computed ? null : property,
                info,
            );
        }

        if (
            object.type === 'MemberExpression' &&
            WAYFINDER_MEMBERS.has(property)
        ) {
            return calleeTarget(object, info);
        }
    }

    return null;
}

/**
 * The Wayfinder targets an `:action` expression can post to: one, or one per
 * branch of a ternary (`representative ? repStore() : store()`), each with the
 * value its branch assumes for a bare identifier test.
 *
 * @returns {{ target: object, assume: Map<string, boolean> }[]}
 */
function resolveAction(expression, info) {
    let node;

    try {
        node = parseExpression(expression, { plugins: ['typescript'] });
    } catch {
        return [];
    }

    const branches = [];
    const visit = (current, assume) => {
        current = unwrapTs(current);

        if (current.type === 'ConditionalExpression') {
            const test = unwrapTs(current.test);
            const name = test.type === 'Identifier' ? test.name : null;
            const withValue = (value) =>
                name ? new Map([...assume, [name, value]]) : assume;
            visit(current.consequent, withValue(true));
            visit(current.alternate, withValue(false));

            return;
        }

        if (current.type === 'CallExpression') {
            const callee = unwrapTs(current.callee);

            if (callee.type === 'ConditionalExpression') {
                visit(
                    {
                        ...callee,
                        consequent: {
                            type: 'CallExpression',
                            callee: callee.consequent,
                        },
                        alternate: {
                            type: 'CallExpression',
                            callee: callee.alternate,
                        },
                    },
                    assume,
                );

                return;
            }

            const target = calleeTarget(callee, info);

            if (target) {
                branches.push({ target, assume });

                return;
            }
        }

        branches.push({ target: null, assume });
    };
    visit(node, new Map());

    return branches;
}

function directive(node, name, arg) {
    return node.props.find(
        (prop) =>
            prop.type === NODE_DIRECTIVE &&
            prop.name === name &&
            (arg === undefined ? !prop.arg : prop.arg?.content === arg),
    );
}

function staticAttribute(node, name) {
    return node.props.find(
        (prop) => prop.type === NODE_ATTRIBUTE && prop.name === name,
    );
}

function formAction(node, info) {
    const bound = directive(node, 'bind', 'action');

    if (bound) {
        return {
            expression: bound.exp?.content ?? '',
            branches: resolveAction(bound.exp?.content ?? '', info),
        };
    }

    const spread = directive(node, 'bind');

    if (spread && /\.form\s*\(/.test(spread.exp?.content ?? '')) {
        return {
            expression: spread.exp.content,
            branches: resolveAction(spread.exp.content, info),
        };
    }

    return null;
}

/* ------------------------------------------------------------------ walking */

/**
 * The attributes one element ends up with, in binding order: static
 * attributes, `:x` bindings and `v-bind="$attrs"` (the parent's fallthrough).
 */
function elementAttributes(node, ctx) {
    const attributes = new Map();

    for (const prop of node.props) {
        if (prop.type === NODE_ATTRIBUTE) {
            attributes.set(prop.name, prop.value ? prop.value.content : '');
        } else if (prop.type === NODE_DIRECTIVE && prop.name === 'bind') {
            if (prop.arg?.content) {
                attributes.set(prop.arg.content, {
                    expression: prop.exp?.content ?? prop.arg.content,
                });
            } else if (prop.exp?.content === '$attrs') {
                for (const [key, value] of ctx.attrs) {
                    attributes.set(key, { value });
                }
            }
        }
    }

    if (ctx.rootFallthrough === node) {
        for (const [key, value] of ctx.attrs) {
            if (!attributes.has(key)) {
                attributes.set(key, { value });
            }
        }
    }

    return attributes;
}

function attributeValue(attributes, name, scope) {
    if (!attributes.has(name)) {
        return undefined;
    }

    const raw = attributes.get(name);

    if (typeof raw === 'string') {
        return raw;
    }

    if ('value' in raw) {
        return raw.value;
    }

    return evaluate(raw.expression, scope);
}

function textOf(node, scope) {
    if (node.type === NODE_TEXT) {
        return node.content;
    }

    if (node.type === NODE_INTERPOLATION) {
        const value = evaluate(node.content.content, scope);

        return value === UNKNOWN
            ? `{{ ${node.content.content.trim()} }}`
            : String(value ?? '');
    }

    if (node.type === NODE_ELEMENT) {
        if (node.tag === 'RequiredMark') {
            return '';
        }

        return (node.children ?? [])
            .map((child) => textOf(child, scope))
            .join('');
    }

    return '';
}

function markOf(node, scope, info) {
    let found = false;
    let dynamic = false;
    const visit = (child) => {
        if (child.type !== NODE_ELEMENT) {
            return;
        }

        if (child.tag === 'RequiredMark' || child.tag === 'required-mark') {
            const condition = directive(child, 'if');
            const value = condition
                ? evaluate(condition.exp?.content, scope)
                : true;

            if (value === UNKNOWN) {
                dynamic = true;
            } else if (value) {
                found = true;
            }
        }

        if (child.tag === 'span' && staticAttribute(child, 'aria-hidden')) {
            const text = child.children
                .map((c) => textOf(c, scope))
                .join('')
                .trim();

            if (text === '*') {
                found = true;
            }
        }

        child.children?.forEach(visit);
    };
    node.children.forEach(visit);
    void info;

    return found ? true : dynamic ? 'dynamic' : false;
}

function loopValues(node, ctx) {
    const loop = directive(node, 'for');

    if (!loop) {
        return null;
    }

    const match = (loop.exp?.content ?? '').match(
        /^\s*\(?\s*([\w$]+)(?:\s*,\s*[\w$]+)*\s*\)?\s+(?:in|of)\s+(.+)$/s,
    );

    if (!match) {
        return { variable: null, values: [UNKNOWN] };
    }

    const source = evaluate(match[2], ctx.scope);

    if (Array.isArray(source)) {
        return { variable: match[1], values: source };
    }

    return { variable: match[1], values: [UNKNOWN] };
}

function conditionOf(node, ctx) {
    const branch = directive(node, 'if') ?? directive(node, 'else-if');

    if (!branch) {
        return { skip: false, conditional: false };
    }

    const value = evaluate(branch.exp?.content, ctx.scope);

    if (value === UNKNOWN) {
        return {
            skip: false,
            conditional: true,
            expression: branch.exp?.content,
        };
    }

    return { skip: !value, conditional: false };
}

function walkChildren(children, ctx, form) {
    let previousSettled = null;

    for (const child of children ?? []) {
        if (child.type !== NODE_ELEMENT) {
            continue;
        }

        const isIf = !!directive(child, 'if');
        const isElse =
            !!directive(child, 'else') || !!directive(child, 'else-if');

        if (isIf) {
            previousSettled = null;
        }

        if (isElse && previousSettled === true) {
            continue;
        }

        const condition = conditionOf(child, ctx);

        if (isIf || isElse) {
            if (condition.skip) {
                if (previousSettled !== 'unknown') {
                    previousSettled = false;
                }

                continue;
            }

            if (!directive(child, 'else')) {
                previousSettled = condition.conditional ? 'unknown' : true;
            }
        }

        const loop = loopValues(child, ctx);

        if (loop) {
            for (const value of loop.values) {
                const scope = new Map(ctx.scope);

                if (loop.variable) {
                    scope.set(loop.variable, value);
                }

                walkElement(
                    child,
                    {
                        ...ctx,
                        scope,
                        conditional:
                            ctx.conditional ||
                            condition.conditional ||
                            value === UNKNOWN,
                    },
                    form,
                );
            }

            continue;
        }

        walkElement(
            child,
            { ...ctx, conditional: ctx.conditional || condition.conditional },
            form,
        );
    }
}

function location(ctx, node) {
    return ctx.site ?? { file: rel(ctx.info.file), line: node.loc.start.line };
}

/** A form element of its own (another `:action`), scanned as its own form. */
function isSeparateForm(node, ctx) {
    if (node === ctx.formRoot) {
        return false;
    }

    const action = formAction(node, ctx.info);

    return (
        action !== null &&
        !(ctx.info.declaresActionProp && action.expression.trim() === 'action')
    );
}

/**
 * A component form root's named slots other than `default` (ResourceFormPage's
 * `#header-actions`, `#after-form`) render outside its `<form>`.
 */
function isOutsideSlot(node) {
    if (node.type !== NODE_ELEMENT || node.tag !== 'template') {
        return false;
    }

    const slot = node.props.find(
        (prop) => prop.type === NODE_DIRECTIVE && prop.name === 'slot',
    );

    return slot !== undefined && (slot.arg?.content ?? 'default') !== 'default';
}

function walkElement(node, ctx, form) {
    const tag = node.tag;

    if (isSeparateForm(node, ctx)) {
        return;
    }

    if (NATIVE_CONTROLS.has(tag)) {
        const attributes = elementAttributes(node, ctx);
        const name = attributeValue(attributes, 'name', ctx.scope);
        const id = attributeValue(attributes, 'id', ctx.scope);
        const type = attributeValue(attributes, 'type', ctx.scope);
        const required = attributeValue(attributes, 'required', ctx.scope);
        const ariaRequired = attributeValue(
            attributes,
            'aria-required',
            ctx.scope,
        );
        const disabled = attributeValue(attributes, 'disabled', ctx.scope);
        const multiple = attributeValue(attributes, 'multiple', ctx.scope);
        const control = {
            multiple: multiple !== undefined && presence(multiple) === true,
            name:
                typeof name === 'string'
                    ? name
                    : name === undefined
                      ? null
                      : UNKNOWN,
            id: typeof id === 'string' ? id : null,
            type:
                tag === 'input'
                    ? typeof type === 'string'
                        ? type
                        : type === undefined
                          ? 'text'
                          : 'dynamic'
                    : tag,
            required:
                required === undefined
                    ? ariaRequired === 'true' || ariaRequired === true
                    : presence(required),
            disabled: disabled === undefined ? false : presence(disabled),
            ...location(ctx, node),
            conditional: ctx.conditional,
            instance: ctx.instance,
            node,
        };
        ctx.controls.push(control);

        return;
    }

    if (tag === 'fieldset') {
        // A checkbox or radio group is labelled by its legend.
        const before = ctx.controls.length;
        walkChildren(node.children, ctx, form);
        const legend = node.children.find(
            (child) => child.type === NODE_ELEMENT && child.tag === 'legend',
        );

        if (legend) {
            const group = {
                text: textOf(legend, ctx.scope).replace(/\s+/g, ' ').trim(),
                mark: markOf(legend, ctx.scope, ctx.info),
            };

            for (const control of ctx.controls.slice(before)) {
                if (control.type === 'checkbox' || control.type === 'radio') {
                    control.group ??= group;
                }
            }
        }

        return;
    }

    if (tag === 'label') {
        const forValue = attributeValue(
            elementAttributes(node, ctx),
            'for',
            ctx.scope,
        );
        const before = ctx.controls.length;
        walkChildren(node.children, ctx, form);
        ctx.labels.push({
            for: typeof forValue === 'string' ? forValue : null,
            text: textOf(node, ctx.scope).replace(/\s+/g, ' ').trim(),
            mark: markOf(node, ctx.scope, ctx.info),
            wraps: ctx.controls.slice(before),
            instance: ctx.instance,
        });

        return;
    }

    if (ctx.depth > 0 && ctx.info.declaresActionProp) {
        const action = directive(node, 'bind', 'action');

        if (action && action.exp?.content === 'action') {
            walkChildren(node.children, ctx, form);

            return;
        }
    }

    const componentFile =
        /^[A-Z]/.test(tag) || tag.includes('-')
            ? resolveComponentFile(ctx.info, tag)
            : null;
    const isComponentRoot = node === ctx.formRoot && ctx.formRootComponent;
    const inner = isComponentRoot ? { ...ctx, formRootComponent: false } : ctx;
    // Slot content belongs to the parent's scope.
    walkChildren(
        isComponentRoot
            ? node.children.filter((child) => !isOutsideSlot(child))
            : node.children,
        inner,
        form,
    );

    if (
        componentFile &&
        ctx.depth < MAX_DEPTH &&
        !ctx.stack.includes(componentFile)
    ) {
        followComponent(node, componentFile, inner, form);
    }
}

function followComponent(node, componentFile, ctx, form) {
    const child = loadSfc(componentFile);
    const base = path.basename(componentFile);
    const scope = new Map(child.constants);
    const attrs = new Map();
    const given = new Map();

    for (const prop of node.props) {
        if (prop.type === NODE_ATTRIBUTE) {
            given.set(camelize(prop.name), {
                raw: prop.value ? prop.value.content : '',
                name: prop.name,
            });
        } else if (
            prop.type === NODE_DIRECTIVE &&
            prop.name === 'bind' &&
            prop.arg?.content
        ) {
            given.set(camelize(prop.arg.content), {
                raw: evaluate(prop.exp?.content ?? prop.arg.content, ctx.scope),
                name: prop.arg.content,
            });
        } else if (
            prop.type === NODE_DIRECTIVE &&
            prop.name === 'bind' &&
            prop.exp?.content === '$attrs'
        ) {
            for (const [key, value] of ctx.attrs) {
                given.set(camelize(key), { raw: value, name: key });
            }
        } else if (prop.type === NODE_DIRECTIVE && prop.name === 'model') {
            given.set(
                prop.arg?.content ? camelize(prop.arg.content) : 'modelValue',
                { raw: UNKNOWN, name: 'modelValue' },
            );
        }
    }

    for (const [name, declaration] of child.props) {
        if (given.has(name)) {
            let value = given.get(name).raw;

            if (
                declaration.boolean &&
                (value === '' || value === hyphenate(name))
            ) {
                value = true;
            }

            scope.set(name, value);
        } else if (child.defaults.has(name)) {
            scope.set(name, child.defaults.get(name));
        } else {
            scope.set(name, declaration.boolean ? false : undefined);
        }
    }

    for (const [name, { raw, name: original }] of given) {
        if (
            !child.props.has(name) &&
            !['class', 'style', 'key'].includes(name)
        ) {
            attrs.set(original, raw);
        }
    }

    const site =
        ctx.site ??
        (FIELD_COMPONENTS.has(base)
            ? { file: rel(ctx.info.file), line: node.loc.start.line }
            : null);

    if (COMPOSITE_FIELDS[base]) {
        const spec = COMPOSITE_FIELDS[base];
        const nameValue = spec.name
            .map((key) => scope.get(key))
            .find((value) => value !== undefined);
        const label = scope.get('label');
        const required = presence(scope.get('required') ?? false);
        ctx.controls.push({
            name:
                nameValue === undefined
                    ? null
                    : typeof nameValue === 'string'
                      ? nameValue
                      : UNKNOWN,
            id: null,
            type: base.replace('.vue', ''),
            required,
            disabled: false,
            file: site.file,
            line: site.line,
            conditional: ctx.conditional,
            instance: ctx.instance,
            composite: {
                mark: required,
                label: typeof label === 'string' ? label : null,
            },
        });

        return;
    }

    if (!child.template) {
        return;
    }

    const rootElements = child.template.children.filter(
        (c) => c.type === NODE_ELEMENT,
    );
    const usesAttrs = JSON.stringify(child.template, (key, value) =>
        key === 'loc' || key === 'parent' ? undefined : value,
    ).includes('"$attrs"');
    const instance = Symbol(base);
    const childCtx = {
        ...ctx,
        info: child,
        scope,
        attrs,
        rootFallthrough:
            child.inheritAttrs && !usesAttrs && rootElements.length === 1
                ? rootElements[0]
                : null,
        depth: ctx.depth + 1,
        stack: [...ctx.stack, componentFile],
        site,
        instance,
    };
    walkChildren(child.template.children, childCtx, form);
}

/* ------------------------------------------------------------------- forms */

function associate(controls, labels) {
    for (const control of controls) {
        if (control.composite) {
            control.mark = control.composite.mark;
            control.label = control.composite.label;
            continue;
        }

        if (control.group) {
            control.mark = control.group.mark;
            control.label = control.group.text;
            continue;
        }

        const sameInstance = labels.filter(
            (label) => label.instance === control.instance,
        );
        const label =
            sameInstance.find((candidate) =>
                candidate.wraps.includes(control),
            ) ??
            (control.id
                ? sameInstance.find((candidate) => candidate.for === control.id)
                : undefined);
        control.mark = label ? label.mark : false;
        control.label = label ? label.text : null;
    }
}

function moduleOf(file) {
    const match = file.match(/^resources\/js\/pages\/(.+)\/[^/]+\.vue$/);

    if (!match) {
        return 'components';
    }

    const parts = match[1].split('/');

    if (parts[0] === 'Store' && parts[1] === 'Admin') {
        return `Store/Admin/${parts[2] ?? ''}`.replace(/\/$/, '');
    }

    return parts.slice(0, 2).join('/');
}

function scanFile(file) {
    const info = loadSfc(file);

    if (!info.template) {
        return [];
    }

    const forms = [];
    const visit = (node) => {
        if (node.type !== NODE_ELEMENT) {
            return;
        }

        const action = formAction(node, info);

        if (
            action &&
            !(info.declaresActionProp && action.expression.trim() === 'action')
        ) {
            for (const branch of action.branches.length
                ? action.branches
                : [{ target: null, assume: new Map() }]) {
                const ctx = {
                    info,
                    scope: new Map([
                        ...info.constants,
                        ...[...info.props.keys()].map((name) => [
                            name,
                            UNKNOWN,
                        ]),
                        ...branch.assume,
                    ]),
                    attrs: new Map(),
                    rootFallthrough: null,
                    controls: [],
                    labels: [],
                    depth: 0,
                    stack: [file],
                    site: null,
                    instance: Symbol('page'),
                    conditional: false,
                };
                walkElement(
                    node,
                    {
                        ...ctx,
                        formRoot: node,
                        formRootComponent:
                            node.tag !== 'Form' && node.tag !== 'form',
                    },
                    null,
                );
                associate(ctx.controls, ctx.labels);
                forms.push({
                    file: rel(file),
                    line: node.loc.start.line,
                    module: moduleOf(rel(file)),
                    tag: node.tag,
                    action: action.expression.replace(/\s+/g, ' ').trim(),
                    target: branch.target,
                    controls: ctx.controls
                        .map((control) => ({
                            name:
                                control.name === UNKNOWN ? null : control.name,
                            name_resolved:
                                control.name !== UNKNOWN &&
                                control.name !== null,
                            type: control.type,
                            required: control.required,
                            mark: control.mark ?? false,
                            label: control.label ?? null,
                            multiple: control.multiple ?? false,
                            disabled: control.disabled,
                            conditional: control.conditional,
                            file: control.file,
                            line: control.line,
                        }))
                        .filter(
                            (control) =>
                                control.type !== 'submit' &&
                                control.type !== 'button',
                        ),
                });
            }
        }

        // Forms in a form root's other slots (`#after-form`) are forms of their own.
        node.children?.forEach(visit);
    };
    info.template.children.forEach(visit);

    return forms;
}

function vueFiles(directory) {
    const out = [];

    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const full = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            out.push(...vueFiles(full));
        } else if (entry.name.endsWith('.vue')) {
            out.push(full);
        }
    }

    return out;
}

const files = [
    ...vueFiles(path.join(jsRoot, 'pages')),
    ...vueFiles(path.join(jsRoot, 'components')),
].sort();
const forms = [];
const errors = [];

for (const file of files) {
    try {
        forms.push(...scanFile(file));
    } catch (error) {
        errors.push({
            file: rel(file),
            message: String(error?.message ?? error),
        });
    }
}

process.stdout.write(JSON.stringify({ files: files.length, forms, errors }));
