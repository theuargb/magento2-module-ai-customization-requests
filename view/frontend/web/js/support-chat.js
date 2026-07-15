define(['Perspective_AiCustomizationRequests/js/chat-service'], function (chatService) {
    'use strict';

    function createElement(tag, className, text) {
        var element = document.createElement(tag);

        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    }

    function normalizeMarkdownUrl(url) {
        var value = url.trim();

        if (/^www\./i.test(value)) {
            return 'https://' + value;
        }
        if (/^(https?:|mailto:|tel:|\/|#)/i.test(value) || !/^[a-z][a-z0-9+.-]*:/i.test(value)) {
            return value;
        }

        return null;
    }

    function appendInlineMarkdown(container, text) {
        var tokenPattern = /(\[[^\]]+\]\([^\s)]+\)|(?:https?:\/\/|www\.)[^\s<]+|\*\*[^*]+\*\*|`[^`]+`|\*[^*]+\*)/g;
        var cursor = 0;
        var match;

        while ((match = tokenPattern.exec(text)) !== null) {
            if (match.index > cursor) {
                container.appendChild(document.createTextNode(text.slice(cursor, match.index)));
            }

            var token = match[0];
            var link = /^\[([^\]]+)\]\(([^\s)]+)\)$/.exec(token);
            var href = link ? normalizeMarkdownUrl(link[2]) : normalizeMarkdownUrl(token);
            var element;

            if (href && (link || /^(?:https?:\/\/|www\.)/i.test(token))) {
                element = createElement('a', 'perspective-ai-chat__markdown-link', link ? link[1] : token);
                element.href = href;
                element.target = '_blank';
                element.rel = 'noopener';
            } else if (link) {
                element = createElement('span', '', token);
            } else if (token.indexOf('**') === 0) {
                element = createElement('strong', '', token.slice(2, -2));
            } else if (token.charAt(0) === '`') {
                element = createElement('code', '', token.slice(1, -1));
            } else {
                element = createElement('em', '', token.slice(1, -1));
            }

            container.appendChild(element);
            cursor = tokenPattern.lastIndex;
        }

        if (cursor < text.length) {
            container.appendChild(document.createTextNode(text.slice(cursor)));
        }
    }

    function tableCells(line) {
        return line.trim().replace(/^\||\|$/g, '').split('|').map(function (cell) {
            return cell.trim();
        });
    }

    function isTableDivider(line) {
        return /^\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(line);
    }

    function appendTable(container, headerCells, rows) {
        var wrapper = createElement('div', 'perspective-ai-chat__markdown-table-wrap');
        var table = createElement('table', 'perspective-ai-chat__markdown-table');
        var head = createElement('thead');
        var headerRow = createElement('tr');

        headerCells.forEach(function (cell) {
            var header = createElement('th');
            appendInlineMarkdown(header, cell);
            headerRow.appendChild(header);
        });
        head.appendChild(headerRow);
        table.appendChild(head);

        var body = createElement('tbody');
        rows.forEach(function (cells) {
            var row = createElement('tr');
            headerCells.forEach(function (_, index) {
                var cell = createElement('td');
                appendInlineMarkdown(cell, cells[index] || '');
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
        table.appendChild(body);
        wrapper.appendChild(table);
        container.appendChild(wrapper);
    }

    function renderMarkdown(container, content) {
        var lines = String(content || '').replace(/\r\n?/g, '\n').split('\n');
        var index = 0;

        while (index < lines.length) {
            var line = lines[index];
            var heading = /^(#{1,3})\s+(.+)$/.exec(line);
            var unordered = /^\s*[-*]\s+(.+)$/.exec(line);
            var ordered = /^\s*\d+[.)]\s+(.+)$/.exec(line);

            if (!line.trim()) {
                index += 1;
            } else if (index + 1 < lines.length && line.indexOf('|') !== -1 && isTableDivider(lines[index + 1])) {
                var rows = [];
                var headerCells = tableCells(line);
                index += 2;
                while (index < lines.length && lines[index].indexOf('|') !== -1 && lines[index].trim()) {
                    rows.push(tableCells(lines[index]));
                    index += 1;
                }
                appendTable(container, headerCells, rows);
            } else if (heading) {
                var level = heading[1].length === 1 ? 'h3' : 'h4';
                var title = createElement(level, 'perspective-ai-chat__markdown-heading');
                appendInlineMarkdown(title, heading[2]);
                container.appendChild(title);
                index += 1;
            } else if (unordered || ordered) {
                var list = createElement(ordered ? 'ol' : 'ul', 'perspective-ai-chat__markdown-list');
                var pattern = ordered ? /^\s*\d+[.)]\s+(.+)$/ : /^\s*[-*]\s+(.+)$/;

                while (index < lines.length) {
                    var listItem = pattern.exec(lines[index]);
                    if (!listItem) {
                        break;
                    }
                    var item = createElement('li');
                    appendInlineMarkdown(item, listItem[1]);
                    list.appendChild(item);
                    index += 1;
                }
                container.appendChild(list);
            } else {
                var paragraphLines = [];
                while (index < lines.length && lines[index].trim() &&
                    !/^(#{1,3})\s+/.test(lines[index]) &&
                    !/^\s*(?:[-*]\s+|\d+[.)]\s+)/.test(lines[index]) &&
                    !(index + 1 < lines.length && lines[index].indexOf('|') !== -1 && isTableDivider(lines[index + 1]))) {
                    paragraphLines.push(lines[index]);
                    index += 1;
                }
                var paragraph = createElement('p', 'perspective-ai-chat__markdown-paragraph');
                appendInlineMarkdown(paragraph, paragraphLines.join(' '));
                container.appendChild(paragraph);
            }
        }
    }

    function readCredentials(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key) || 'null');
        } catch (error) {
            window.localStorage.removeItem(key);
            return null;
        }
    }

    function createBrandMark(className, widget) {
        var mark = createElement('span', className, widget.logoUrl ? undefined : widget.icon);

        if (widget.logoUrl) {
            var logo = createElement('img', className + '-image');
            logo.src = widget.logoUrl;
            logo.alt = widget.title;
            mark.appendChild(logo);
        }

        return mark;
    }

    return function (config, mount) {
        var widget = config.widget;
        var copy = widget.copy;
        var storageKey = 'perspective_ai_chat_credentials';
        var credentials = readCredentials(storageKey);
        var messages = [];
        var selectedProduct = null;
        var isReady = false;
        var isSending = false;

        var root = createElement('div', 'perspective-ai-chat');
        Object.keys(widget.colors).forEach(function (name) {
            var cssVariable = {
                primary: '--chat-blue',
                primaryHover: '--chat-blue-dark',
                onPrimary: '--chat-on-primary',
                iconBackground: '--chat-icon-background',
                iconForeground: '--chat-icon-foreground',
                surface: '--chat-surface',
                canvas: '--chat-canvas',
                text: '--chat-ink',
                muted: '--chat-muted',
                border: '--chat-line'
            }[name];

            if (cssVariable) {
                root.style.setProperty(cssVariable, widget.colors[name]);
            }
        });
        var launcher = createElement('button', 'perspective-ai-chat__launcher');
        var launcherMark = createBrandMark('perspective-ai-chat__launcher-mark', widget);
        var launcherLabel = createElement('span', 'perspective-ai-chat__launcher-label', widget.title);
        launcher.type = 'button';
        launcher.setAttribute('aria-expanded', 'false');
        launcher.appendChild(launcherMark);
        launcher.appendChild(launcherLabel);

        var panel = createElement('section', 'perspective-ai-chat__panel');
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'perspective-ai-chat-title');

        var header = createElement('header', 'perspective-ai-chat__header');
        var identity = createElement('div', 'perspective-ai-chat__identity');
        var brandMark = createBrandMark('perspective-ai-chat__brand-mark', widget);
        var brandCopy = createElement('div', 'perspective-ai-chat__brand-copy');
        var title = createElement('strong', 'perspective-ai-chat__title', widget.title);
        title.id = 'perspective-ai-chat-title';
        var status = createElement('span', 'perspective-ai-chat__status', copy.connecting);
        var actions = createElement('div', 'perspective-ai-chat__actions');
        var clear = createElement('button', 'perspective-ai-chat__clear', copy.clear);
        clear.type = 'button';
        var close = createElement('button', 'perspective-ai-chat__close');
        close.type = 'button';
        close.setAttribute('aria-label', copy.close);
        close.appendChild(createElement('span', '', '×'));
        brandCopy.appendChild(title);
        brandCopy.appendChild(status);
        identity.appendChild(brandMark);
        identity.appendChild(brandCopy);
        actions.appendChild(clear);
        actions.appendChild(close);
        header.appendChild(identity);
        header.appendChild(actions);

        var history = createElement('div', 'perspective-ai-chat__history');
        history.setAttribute('aria-live', 'polite');
        history.setAttribute('aria-busy', 'false');

        var composer = createElement('div', 'perspective-ai-chat__composer');
        var contextBar = createElement('div', 'perspective-ai-chat__context');
        contextBar.hidden = true;
        var form = createElement('form', 'perspective-ai-chat__form');
        var input = createElement('textarea', 'perspective-ai-chat__input');
        input.rows = 1;
        input.placeholder = copy.inputPlaceholder;
        input.maxLength = 2000;
        input.disabled = true;
        var send = createElement('button', 'perspective-ai-chat__send', copy.send);
        send.type = 'submit';
        send.disabled = true;
        var helper = createElement('p', 'perspective-ai-chat__helper', copy.helper);
        form.appendChild(input);
        form.appendChild(send);
        composer.appendChild(contextBar);
        composer.appendChild(form);
        composer.appendChild(helper);

        panel.appendChild(header);
        panel.appendChild(history);
        panel.appendChild(composer);
        root.appendChild(launcher);
        root.appendChild(panel);
        mount.appendChild(root);

        function setStatus(label, state) {
            status.textContent = label;
            status.dataset.state = state || '';
        }

        function setReady(ready) {
            isReady = ready;
            input.disabled = !ready || isSending;
            send.disabled = !ready || isSending || input.value.trim() === '';
            history.setAttribute('aria-busy', ready ? 'false' : 'true');
            setStatus(ready ? copy.ready : copy.connecting, ready ? 'ready' : 'loading');
        }

        function resizeInput() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 112) + 'px';
            send.disabled = !isReady || isSending || input.value.trim() === '';
        }

        function choosePrompt(promptText) {
            input.value = promptText;
            resizeInput();
            input.focus();
        }

        function renderWelcome() {
            var welcome = createElement('div', 'perspective-ai-chat__welcome');
            var intro = createElement('div', 'perspective-ai-chat__welcome-intro');
            intro.appendChild(createElement('span', 'perspective-ai-chat__eyebrow', copy.welcomeEyebrow));
            intro.appendChild(createElement('h2', 'perspective-ai-chat__welcome-title', copy.welcomeTitle));
            intro.appendChild(createElement('p', 'perspective-ai-chat__welcome-copy', copy.welcomeCopy));
            welcome.appendChild(intro);

            var brief = createElement('div', 'perspective-ai-chat__brief');
            copy.prompts.forEach(function (item) {
                var prompt = createElement('button', 'perspective-ai-chat__prompt');
                prompt.type = 'button';
                prompt.appendChild(createElement('span', 'perspective-ai-chat__prompt-label', item.label));
                prompt.appendChild(createElement('span', 'perspective-ai-chat__prompt-copy', item.text));
                prompt.appendChild(createElement('span', 'perspective-ai-chat__prompt-arrow', '→'));
                prompt.addEventListener('click', function () { choosePrompt(item.text); });
                brief.appendChild(prompt);
            });
            welcome.appendChild(brief);
            history.appendChild(welcome);
        }

        function selectProduct(mention) {
            selectedProduct = mention;
            contextBar.replaceChildren();
            contextBar.hidden = false;
            contextBar.appendChild(createElement('span', 'perspective-ai-chat__context-label', copy.contextLabel));
            contextBar.appendChild(createElement('strong', 'perspective-ai-chat__context-name', mention.name));
            var remove = createElement('button', 'perspective-ai-chat__context-remove', copy.contextRemove);
            remove.type = 'button';
            remove.addEventListener('click', function () {
                selectedProduct = null;
                contextBar.hidden = true;
                contextBar.replaceChildren();
                input.focus();
            });
            contextBar.appendChild(remove);
            input.focus();
        }

        function renderProduct(mention) {
            var card = createElement('div', 'perspective-ai-chat__product');
            var thumbnail = createElement('div', 'perspective-ai-chat__product-image');
            var fallback = createElement(
                'span',
                'perspective-ai-chat__product-image-fallback',
                (mention.name || mention.sku || '?').charAt(0).toUpperCase()
            );
            thumbnail.appendChild(fallback);
            if (mention.image_url) {
                var image = createElement('img');
                image.src = mention.image_url;
                image.alt = '';
                image.loading = 'lazy';
                thumbnail.appendChild(image);
            }
            var details = createElement('div', 'perspective-ai-chat__product-details');
            details.appendChild(createElement('span', 'perspective-ai-chat__product-label', copy.productLabel));
            details.appendChild(createElement('strong', 'perspective-ai-chat__product-name', mention.name));
            card.appendChild(thumbnail);
            card.appendChild(details);
            var actions = createElement('div', 'perspective-ai-chat__product-actions');
            var ask = createElement('button', 'perspective-ai-chat__product-ask', copy.productAsk);
            ask.type = 'button';
            ask.addEventListener('click', function () { selectProduct(mention); });
            actions.appendChild(ask);
            if (mention.url) {
                var link = createElement('a', 'perspective-ai-chat__product-link', copy.productView);
                link.href = mention.url;
                link.target = '_blank';
                link.rel = 'noopener';
                actions.appendChild(link);
            }
            card.appendChild(actions);
            return card;
        }

        function renderConversation() {
            history.replaceChildren();
            if (!messages.length) {
                renderWelcome();
                return;
            }

            messages.forEach(function (message) {
                var role = message.role === 'user' ? 'user' : 'assistant';
                var item = createElement('article', 'perspective-ai-chat__message perspective-ai-chat__message--' + role);
                item.appendChild(createElement('span', 'perspective-ai-chat__message-author', role === 'user' ? copy.user : widget.title));
                if (message.isLoading) {
                    var typing = createElement('div', 'perspective-ai-chat__typing');
                    typing.appendChild(createElement('span'));
                    typing.appendChild(createElement('span'));
                    typing.appendChild(createElement('span'));
                    item.appendChild(typing);
                } else {
                    var bubble = createElement('div', 'perspective-ai-chat__bubble');
                    renderMarkdown(bubble, message.content);
                    item.appendChild(bubble);
                }
                (message.product_mentions || []).forEach(function (mention) { item.appendChild(renderProduct(mention)); });
                history.appendChild(item);
            });
            history.scrollTop = history.scrollHeight;
        }

        function renderError(error) {
            history.replaceChildren();
            var state = createElement('div', 'perspective-ai-chat__error');
            state.appendChild(createElement('span', 'perspective-ai-chat__eyebrow', copy.errorEyebrow));
            state.appendChild(createElement('h2', 'perspective-ai-chat__error-title', copy.errorTitle));
            state.appendChild(createElement('p', 'perspective-ai-chat__error-copy', error.message || copy.errorCopy));
            var retry = createElement('button', 'perspective-ai-chat__retry', copy.errorRetry);
            retry.type = 'button';
            retry.addEventListener('click', initializeSession);
            state.appendChild(retry);
            history.appendChild(state);
            setStatus(copy.unavailable, 'error');
        }

        function storeSession(session) {
            if (session.resume_token) {
                credentials = {session_uid: session.session_uid, resume_token: session.resume_token};
                window.localStorage.setItem(storageKey, JSON.stringify(credentials));
            }
            messages = session.messages || [];
            setReady(true);
            renderConversation();
        }

        function createSession() {
            return chatService.startSession(config.graphqlEndpoint).then(storeSession);
        }

        function initializeSession() {
            setReady(false);
            renderConversation();
            if (!credentials) {
                return createSession().catch(renderError);
            }
            return chatService.resumeSession(config.graphqlEndpoint, credentials).then(storeSession).catch(function () {
                credentials = null;
                window.localStorage.removeItem(storageKey);
                return createSession().catch(renderError);
            });
        }

        function openPanel() {
            panel.hidden = false;
            launcher.hidden = true;
            launcher.setAttribute('aria-expanded', 'true');
            renderConversation();
            initializeSession();
        }

        function closePanel() {
            panel.hidden = true;
            launcher.hidden = false;
            launcher.setAttribute('aria-expanded', 'false');
            launcher.focus();
        }

        function clearSession() {
            credentials = null;
            messages = [];
            selectedProduct = null;
            isSending = false;
            window.localStorage.removeItem(storageKey);
            contextBar.hidden = true;
            contextBar.replaceChildren();
            input.value = '';
            input.style.height = '';
            setReady(false);
            renderConversation();
            initializeSession();
        }

        function submitMessage() {
            var message = input.value.trim();
            if (!message || !credentials || isSending) {
                return;
            }
            isSending = true;
            input.value = '';
            resizeInput();
            messages.push({role: 'user', content: message, product_mentions: []});
            messages.push({role: 'assistant', isLoading: true, product_mentions: []});
            renderConversation();
            input.disabled = true;
            send.disabled = true;
            setStatus(copy.thinking, 'loading');

            chatService.sendMessage(
                config.graphqlEndpoint,
                credentials,
                message,
                selectedProduct ? selectedProduct.sku : null
            ).then(function (payload) {
                messages = payload.session.messages || [];
                selectedProduct = null;
                contextBar.hidden = true;
                contextBar.replaceChildren();
                renderConversation();
                setStatus(copy.ready, 'ready');
            }).catch(function (error) {
                messages = messages.filter(function (item) { return !item.isLoading; });
                messages.push({role: 'assistant', content: error.message || copy.messageSendError, product_mentions: []});
                renderConversation();
                setStatus(copy.messageFailed, 'error');
            }).finally(function () {
                isSending = false;
                input.disabled = false;
                resizeInput();
                input.focus();
            });
        }

        launcher.addEventListener('click', openPanel);
        clear.addEventListener('click', clearSession);
        close.addEventListener('click', closePanel);
        input.addEventListener('input', resizeInput);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                submitMessage();
            }
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitMessage();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                closePanel();
            }
        });

        renderConversation();

        if (config.restoreToken) {
            chatService.restorePreparedCart(config.graphqlEndpoint, config.restoreToken).then(function (result) {
                if (result.success && result.cart_url) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                    window.location.assign(result.cart_url);
                }
            }).catch(renderError);
        }
    };
});
