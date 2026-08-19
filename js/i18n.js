(() => {
    'use strict';
    const config = window.SodiumI18n;
    if (!config || !config.map || config.locale === 'fr') return;
    const map = config.map;
    const blocked = 'script,style,code,pre,textarea,[contenteditable="true"],.message-body,.message-content,.mail-content,.email-body,[data-no-translate]';

    const translateText = node => {
        if (!node.parentElement || node.parentElement.closest(blocked)) return;
        const raw = node.nodeValue || '';
        const trimmed = raw.trim();
        if (!trimmed || !Object.prototype.hasOwnProperty.call(map, trimmed)) return;
        node.nodeValue = raw.replace(trimmed, map[trimmed]);
    };

    const translateElement = root => {
        if (!(root instanceof Element) || root.closest(blocked)) return;
        ['title', 'aria-label', 'placeholder'].forEach(attribute => {
            const value = root.getAttribute(attribute);
            if (value && Object.prototype.hasOwnProperty.call(map, value)) root.setAttribute(attribute, map[value]);
        });
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) translateText(node);
    };

    translateElement(document.body);
    new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) translateText(node);
        else if (node.nodeType === Node.ELEMENT_NODE) translateElement(node);
    }))).observe(document.body, {childList: true, subtree: true});
})();
