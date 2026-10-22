define([], function () {
    'use strict';

    /**
     * Creates an element with an optional class and text content (never HTML).
     *
     * @param {String} tag
     * @param {String} [className]
     * @param {String} [text]
     * @returns {HTMLElement}
     */
    function el(tag, className, text) {
        var element = document.createElement(tag);

        if (className) {
            element.className = className;
        }
        if (text !== undefined && text !== null) {
            element.textContent = text;
        }

        return element;
    }

    /**
     * @param {HTMLElement} parent
     * @param {Array<Node>} children
     * @returns {HTMLElement}
     */
    function append(parent, children) {
        children.forEach(function (child) {
            parent.appendChild(child);
        });

        return parent;
    }

    function button(className, text, onClick) {
        var element = el('button', className, text);

        element.type = 'button';
        if (onClick) {
            element.addEventListener('click', onClick);
        }

        return element;
    }

    return {el: el, append: append, button: button};
});
