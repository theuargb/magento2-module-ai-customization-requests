/**
 * Safe subset of Markdown for assistant replies: headings, lists, tables, paragraphs, links,
 * bold, italic, inline code. Builds DOM nodes only; no HTML strings are ever parsed.
 */
define(['Perspective_AiCustomizationRequests/js/chat-dom'], function (dom) {
    'use strict';

    var B = 'perspective-ai-chat__';
    var INLINE = /(\[[^\]]+\]\([^\s)]+\)|(?:https?:\/\/|www\.)[^\s<]+|\*\*[^*]+\*\*|`[^`]+`|\*[^*]+\*)/g;
    var LINK = /^\[([^\]]+)\]\(([^\s)]+)\)$/;
    var HEADING = /^(#{1,3})\s+(.+)$/;
    var UNORDERED = /^\s*[-*]\s+(.+)$/;
    var ORDERED = /^\s*\d+[.)]\s+(.+)$/;
    var DIVIDER = /^\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*$/;

    /** Allows http(s), mailto, tel, relative and anchor URLs only. */
    function safeUrl(url) {
        var value = url.trim();

        if (/^www\./i.test(value)) {
            return 'https://' + value;
        }

        return /^(https?:|mailto:|tel:|\/|#)/i.test(value) || !/^[a-z][a-z0-9+.-]*:/i.test(value) ? value : null;
    }

    function inline(container, text) {
        var cursor = 0;
        var match;

        INLINE.lastIndex = 0;
        while ((match = INLINE.exec(text)) !== null) {
            if (match.index > cursor) {
                container.appendChild(document.createTextNode(text.slice(cursor, match.index)));
            }
            container.appendChild(inlineToken(match[0]));
            cursor = INLINE.lastIndex;
        }
        if (cursor < text.length) {
            container.appendChild(document.createTextNode(text.slice(cursor)));
        }
    }

    function inlineToken(token) {
        var link = LINK.exec(token);
        var isBareUrl = !link && /^(?:https?:\/\/|www\.)/i.test(token);
        var href = link ? safeUrl(link[2]) : isBareUrl ? safeUrl(token) : null;
        var anchor;

        if (href) {
            anchor = dom.el('a', B + 'md-link', link ? link[1] : token);
            anchor.href = href;
            anchor.rel = 'noopener';
            return anchor;
        }
        if (link) {
            return dom.el('span', '', link[1]);
        }
        if (token.indexOf('**') === 0) {
            return dom.el('strong', '', token.slice(2, -2));
        }
        if (token.charAt(0) === '`') {
            return dom.el('code', '', token.slice(1, -1));
        }

        return dom.el('em', '', token.slice(1, -1));
    }

    function cells(line) {
        return line.trim().replace(/^\||\|$/g, '').split('|').map(function (cell) {
            return cell.trim();
        });
    }

    function isTableStart(lines, index) {
        return index + 1 < lines.length && lines[index].indexOf('|') !== -1 && DIVIDER.test(lines[index + 1]);
    }

    function table(header, rows) {
        var wrap = dom.el('div', B + 'md-table-wrap');
        var tableEl = dom.el('table', B + 'md-table');
        var head = dom.el('tr');
        var body = dom.el('tbody');

        header.forEach(function (text) {
            var th = dom.el('th');

            inline(th, text);
            head.appendChild(th);
        });
        rows.forEach(function (row) {
            var tr = dom.el('tr');

            header.forEach(function (_, index) {
                var td = dom.el('td');

                inline(td, row[index] || '');
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });

        return dom.append(wrap, [dom.append(tableEl, [dom.append(dom.el('thead'), [head]), body])]);
    }

    function isBlockStart(lines, index) {
        return HEADING.test(lines[index]) || UNORDERED.test(lines[index]) || ORDERED.test(lines[index])
            || isTableStart(lines, index);
    }

    /**
     * @param {HTMLElement} container
     * @param {String} content
     */
    function render(container, content) {
        var lines = String(content || '').replace(/\r\n?/g, '\n').split('\n');
        var index = 0;

        while (index < lines.length) {
            var line = lines[index];
            var heading = HEADING.exec(line);
            var pattern = UNORDERED.test(line) ? UNORDERED : ORDERED.test(line) ? ORDERED : null;
            var node;

            if (!line.trim()) {
                index += 1;
                continue;
            }
            if (isTableStart(lines, index)) {
                var header = cells(line);
                var rows = [];

                for (index += 2; index < lines.length && lines[index].trim() && lines[index].indexOf('|') !== -1; index++) {
                    rows.push(cells(lines[index]));
                }
                container.appendChild(table(header, rows));
                continue;
            }
            if (heading) {
                node = dom.el(heading[1].length === 1 ? 'h3' : 'h4', B + 'md-heading');
                inline(node, heading[2]);
                container.appendChild(node);
                index += 1;
                continue;
            }
            if (pattern) {
                node = dom.el(pattern === ORDERED ? 'ol' : 'ul', B + 'md-list');
                for (var item; index < lines.length && (item = pattern.exec(lines[index])); index++) {
                    var li = dom.el('li');

                    inline(li, item[1]);
                    node.appendChild(li);
                }
                container.appendChild(node);
                continue;
            }

            var paragraph = [];

            while (index < lines.length && lines[index].trim() && (!paragraph.length || !isBlockStart(lines, index))) {
                paragraph.push(lines[index]);
                index += 1;
            }
            node = dom.el('p', B + 'md-p');
            inline(node, paragraph.join(' '));
            container.appendChild(node);
        }
    }

    return {render: render};
});
