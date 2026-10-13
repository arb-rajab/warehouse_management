/** Client editing/paste safety; server clean_html() remains authoritative. */
export function safeEditorUrl(value: string, image = false): boolean {
    const compact = value.replace(/[\u0000-\u0020\u007f-\u009f]/g, '');

    if (!compact) {
        return false;
    }

    if (
        /^data:image\/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$/i.test(compact)
    ) {
        return image;
    }

    return (
        !/^[a-z][a-z0-9+.-]*:/i.test(compact) ||
        (image ? /^https?:/i : /^(https?|mailto|tel):/i).test(compact)
    );
}
export function safeEditorHtml(html: string): string {
    const document = new DOMParser().parseFromString(html, 'text/html');
    const tags = new Set(
        'a abbr b blockquote br caption cite code col colgroup dd del div dl dt em figcaption figure h1 h2 h3 h4 h5 h6 hr i img ins li mark ol p pre q s small span strike strong sub sup table tbody td tfoot th thead tr u ul'.split(
            ' ',
        ),
    );
    const dropped = new Set(
        'applet audio base button canvas dialog embed form frame frameset head iframe input link math meta noembed noframes noscript object optgroup option plaintext script select source style svg template textarea title track video xmp'.split(
            ' ',
        ),
    );
    const css = new Set(
        'background-color border border-bottom border-collapse border-color border-left border-right border-style border-top border-width color font-family font-size font-style font-weight height letter-spacing line-height list-style-type margin margin-bottom margin-left margin-right margin-top max-width padding padding-bottom padding-left padding-right padding-top text-align text-decoration text-indent vertical-align width'.split(
            ' ',
        ),
    );
    const attrs: Record<string, string[]> = {
        a: ['href', 'target'],
        img: ['src', 'alt', 'height', 'width'],
        ol: ['start', 'type'],
        td: ['align', 'colspan', 'rowspan', 'valign'],
        th: ['align', 'colspan', 'rowspan', 'scope', 'valign'],
        col: ['span', 'width'],
        colgroup: ['span'],
    };

    for (const element of Array.from(document.body.querySelectorAll('*'))) {
        const tag = element.localName;

        if (
            element.namespaceURI !== 'http://www.w3.org/1999/xhtml' ||
            dropped.has(tag)
        ) {
            element.remove();
            continue;
        }

        if (!tags.has(tag)) {
            element.replaceWith(...element.childNodes);
            continue;
        }

        for (const attribute of Array.from(element.attributes)) {
            const { name, value } = attribute;

            if (
                ![
                    'class',
                    'dir',
                    'lang',
                    'style',
                    'title',
                    ...(attrs[tag] ?? []),
                ].includes(name) ||
                ((name === 'href' || name === 'src') &&
                    !safeEditorUrl(value, name === 'src'))
            ) {
                element.removeAttribute(name);
            } else if (name === 'style') {
                const kept = value
                    .split(';')
                    .filter((declaration) => {
                        const [property, ...rest] = declaration.split(':');
                        const content = rest.join(':').trim();

                        return (
                            css.has(property.trim().toLowerCase()) &&
                            /^[a-z0-9\s#%.,()+\-'"]+$/i.test(content) &&
                            !/url\s*\(|expression|javascript|import|behavior|binding/i.test(
                                content,
                            )
                        );
                    })
                    .join(';');

                if (kept) {
                    element.setAttribute('style', kept);
                } else {
                    element.removeAttribute('style');
                }
            } else if (name === 'target' && value !== '_blank') {
                element.removeAttribute(name);
            }
        }

        if (tag === 'a') {
            element.setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    return document.body.innerHTML;
}
