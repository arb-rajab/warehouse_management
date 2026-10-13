export type Child = Node | string | number | null | undefined | false;

export interface ElementProps {
    class?: string;
    text?: string;
    attrs?: Record<string, string | number | boolean | null | undefined>;
    data?: Record<string, string | number | boolean | null | undefined>;
    /** CSS custom properties only (`--wms-x`): dynamic values reach CSS through variables. */
    vars?: Record<string, string | number>;
}

function applyProps(node: Element, props: ElementProps): void {
    if (props.class) {
        node.className = props.class;
    }

    if (props.text !== undefined) {
        node.textContent = props.text;
    }

    for (const [name, value] of Object.entries(props.attrs ?? {})) {
        setAttr(node, name, value);
    }

    for (const [name, value] of Object.entries(props.data ?? {})) {
        if (value === null || value === undefined || value === false) {
            node.removeAttribute(`data-${name}`);
        } else {
            node.setAttribute(`data-${name}`, String(value));
        }
    }

    for (const [name, value] of Object.entries(props.vars ?? {})) {
        (node as HTMLElement).style.setProperty(name, String(value));
    }
}

export function setAttr(
    node: Element,
    name: string,
    value: string | number | boolean | null | undefined,
): void {
    if (value === null || value === undefined || value === false) {
        node.removeAttribute(name);
    } else {
        node.setAttribute(name, value === true ? '' : String(value));
    }
}

/**
 * Builds an element. Text always goes in as a text node, never as HTML, so a
 * product name from the server cannot inject markup.
 */
export function el<K extends keyof HTMLElementTagNameMap>(
    tag: K,
    props: ElementProps = {},
    ...children: Child[]
): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag);

    applyProps(node, props);
    append(node, ...children);

    return node;
}

export function append(parent: Node, ...children: Child[]): void {
    for (const child of children) {
        if (child === null || child === undefined || child === false) {
            continue;
        }

        (parent as ParentNode).append(
            typeof child === 'string' || typeof child === 'number'
                ? document.createTextNode(String(child))
                : child,
        );
    }
}

/** Replaces all children of a node. */
export function replaceChildrenOf(parent: Node, ...children: Child[]): void {
    while (parent.firstChild) {
        parent.removeChild(parent.firstChild);
    }

    append(parent, ...children);
}

export function toggleClass(node: Element, name: string, on: boolean): void {
    node.classList.toggle(name, on);
}

/** A code or an aisle name keeps its own direction inside an Arabic sentence. */
export function ltr(text: string | number): HTMLElement {
    return el('bdi', { attrs: { dir: 'ltr' }, text: String(text) });
}

export function kbd(...keys: string[]): HTMLElement[] {
    return keys.map((key) => el('kbd', { text: key }));
}

/** Whether the keyboard focus is somewhere the user is typing. */
export function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.isContentEditable) {
        return true;
    }

    if (
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement
    ) {
        return true;
    }

    if (!(target instanceof HTMLInputElement)) {
        return false;
    }

    return target.type !== 'range' && target.type !== 'checkbox';
}
