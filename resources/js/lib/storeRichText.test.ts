import { describe, expect, it } from 'vitest';
import { safeEditorHtml, safeEditorUrl } from './storeRichText';
describe('rich text editing safety', () => {
    it('keeps legacy formatting, tables, images and RTL attributes', () => {
        const html =
            '<h2 dir="rtl">مرحبا</h2><p><u>Underline</u><strong>Bold</strong><span style="color: #123; background-color: #fff">Colour</span></p><table><tbody><tr><td colspan="2">Cell</td></tr></tbody></table><img src="/uploads/image.png" width="10">';
        expect(safeEditorHtml(html)).toContain('<u>Underline</u>');
        expect(safeEditorHtml(html)).toContain('colspan="2"');
        expect(safeEditorHtml(html)).toContain('dir="rtl"');
        expect(safeEditorHtml(html)).toContain('color: #123');
    });
    it('removes executable tags namespaces attributes URLs and CSS from pasted/source HTML', () => {
        const html = safeEditorHtml(
            '<script>attack</script><svg><foreignObject><p>bad</p></foreignObject></svg><img src="javascript:attack()" onerror="attack"><a href="java\nscript:attack()">Link</a><p style="position:fixed;color:red;background:url(attack)">Keep</p><iframe src="https://example.com"></iframe>',
        );
        expect(html).not.toMatch(
            /attack|script|svg|onerror|iframe|position|url\(/,
        );
        expect(html).toContain('color:red');
        expect(html).toContain('Keep');
    });
    it('permits the established URL schemes and existing raster data images', () => {
        expect(safeEditorUrl('mailto:support@example.com')).toBe(true);
        expect(safeEditorUrl('/help')).toBe(true);
        expect(safeEditorUrl('java\tscript:alert(1)')).toBe(false);
        expect(safeEditorUrl('data:text/html,x', true)).toBe(false);
        expect(safeEditorUrl('data:image/png;base64,AAAA', true)).toBe(true);
    });
});
