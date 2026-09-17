(function () {
    'use strict';

    const prefix = 'PRX-';
    function format(value) {
        const text = String(value || '').normalize('NFKC').toUpperCase()
            .replace(/[\u2010-\u2015\u2212\uFE58\uFE63\uFF0D]/g, '-')
            .replace(/\s+/g, '');
        const suffix = text.replace(/^(?:PRX-?)+/, '').replace(/[^A-Z0-9]/g, '').slice(0, 12);
        return prefix + suffix;
    }

    window.initPrxTokenInput = function (input) {
        if (!input) return;
        const update = () => {
            const caret = input.selectionStart;
            const beforeCaret = caret === null ? input.value : input.value.slice(0, caret);
            const value = format(input.value);
            const nextCaret = Math.min(value.length, format(beforeCaret).length);
            input.value = value;
            if (document.activeElement === input) input.setSelectionRange(nextCaret, nextCaret);
        };
        update();
        input.addEventListener('input', event => { if (!event.isComposing) update(); });
        input.addEventListener('compositionend', update);
        input.addEventListener('focus', () => {
            if (input.selectionStart === input.selectionEnd && input.selectionStart < prefix.length) {
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
        input.addEventListener('click', () => {
            if (input.selectionStart === input.selectionEnd && input.selectionStart < prefix.length) {
                input.setSelectionRange(prefix.length, prefix.length);
            }
        });
        input.addEventListener('keyup', () => {
            if (input.selectionStart === input.selectionEnd && input.selectionStart < prefix.length) {
                input.setSelectionRange(prefix.length, prefix.length);
            }
        });
        input.addEventListener('keydown', event => {
            if ((event.key === 'Backspace' || event.key === 'Delete')
                && input.selectionStart <= prefix.length && input.selectionEnd <= prefix.length) {
                event.preventDefault();
            }
        });
    };
})();
