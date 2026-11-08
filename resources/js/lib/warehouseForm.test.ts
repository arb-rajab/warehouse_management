import { describe, expect, it } from 'vitest';
import { parseToken, supportedTokens } from '@/lib/formValidation';
import { warehouseValidation } from '@/lib/warehouseForm';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';

describe('warehouseValidation', () => {
    it('checks the three warehouse fields, and only the name is required', () => {
        expect(Object.keys(warehouseValidation.rules)).toEqual([
            'name',
            'code',
            'address',
        ]);
        expect(warehouseValidation.rules.name).toContain('required');
        expect(warehouseValidation.rules.code).not.toContain('required');
        expect(warehouseValidation.rules.address).not.toContain('required');
    });

    it('mirrors the maximum lengths of the server rules', () => {
        expect(warehouseValidation.rules.name).toContain('max:100');
        expect(warehouseValidation.rules.code).toContain('max:20');
        expect(warehouseValidation.rules.address).toContain('max:255');
    });

    it('uses only rule tokens the engine checks', () => {
        const supported: readonly string[] = supportedTokens;

        for (const tokens of Object.values(warehouseValidation.rules)) {
            for (const token of tokens) {
                expect(supported).toContain(parseToken(token)[0]);
            }
        }
    });

    it.each([
        ['en', en],
        ['ar', ar],
    ])('has the server wording of every rule in %s', (_locale, messages) => {
        const wording = messages.validation.messages as Record<string, unknown>;

        expect(wording.required).toBeTypeOf('string');
        expect(wording.string).toBeTypeOf('string');
        expect((wording.max as Record<string, string>).string).toBeTypeOf(
            'string',
        );
    });
});
