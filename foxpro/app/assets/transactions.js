(() => {
    const transactions = window.foxproTransactions || {};
    const modal = document.querySelector('[data-transaction-modal]');
    const modalTitle = document.querySelector('#transaction-modal-title');
    const modalMeta = document.querySelector('[data-transaction-meta]');
    const modalPayments = document.querySelector('[data-transaction-payments]');
    const modalProducts = document.querySelector('[data-transaction-products]');
    const hoverCards = document.querySelectorAll('.transaction-hover-card');
    let activeHoverCard = null;

    const money = value => Number(value || 0).toFixed(2);

    const textElement = (tag, text, className = '') => {
        const element = document.createElement(tag);
        element.textContent = text;
        if (className) {
            element.className = className;
        }
        return element;
    };

    const hideHover = () => {
        if (!activeHoverCard) {
            return;
        }
        activeHoverCard.classList.remove('is-visible');
        activeHoverCard.setAttribute('aria-hidden', 'true');
        activeHoverCard = null;
    };

    const moveHover = event => {
        if (!activeHoverCard) {
            return;
        }
        const margin = 14;
        const rect = activeHoverCard.getBoundingClientRect();
        let left = event.clientX + margin;
        let top = event.clientY + margin;
        if (left + rect.width > window.innerWidth - margin) {
            left = event.clientX - rect.width - margin;
        }
        if (top + rect.height > window.innerHeight - margin) {
            top = event.clientY - rect.height - margin;
        }
        activeHoverCard.style.left = `${Math.max(margin, left)}px`;
        activeHoverCard.style.top = `${Math.max(margin, top)}px`;
    };

    document.querySelectorAll('.transaction-preview-trigger').forEach(trigger => {
        const card = trigger.parentElement?.querySelector('.transaction-hover-card');
        if (!card) {
            return;
        }
        trigger.addEventListener('mouseenter', event => {
            hideHover();
            activeHoverCard = card;
            card.classList.add('is-visible');
            card.setAttribute('aria-hidden', 'false');
            moveHover(event);
        });
        trigger.addEventListener('mousemove', moveHover);
        trigger.addEventListener('mouseleave', hideHover);
        trigger.addEventListener('focus', () => {
            hideHover();
            activeHoverCard = card;
            card.classList.add('is-visible');
            card.setAttribute('aria-hidden', 'false');
            const rect = trigger.getBoundingClientRect();
            moveHover({ clientX: rect.right, clientY: rect.bottom });
        });
        trigger.addEventListener('blur', hideHover);
    });

    const closeModal = () => {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('transaction-modal-open');
    };

    const openModal = id => {
        const transaction = transactions[id];
        if (!transaction || !modal) {
            return;
        }
        modalTitle.textContent = `Nota #${transaction.note_number}`;
        modalMeta.replaceChildren();
        [
            ['Operator', transaction.operator],
            ['Data si ora', `${transaction.date} ${transaction.time}`.trim()],
            ['Masa', transaction.table || '-'],
            ['Gestiune', transaction.management || '-'],
            ['Total nota', `${money(transaction.total)} RON`],
        ].forEach(([label, value]) => {
            const item = document.createElement('div');
            item.append(textElement('span', label), textElement('strong', value));
            modalMeta.append(item);
        });

        modalPayments.replaceChildren();
        [
            ['Numerar', transaction.cash],
            ['Card', transaction.card],
            ['Protocol', transaction.protocol],
            ['Alte plati', transaction.other],
        ].forEach(([label, value]) => {
            if (Number(value || 0) <= 0.004) {
                return;
            }
            const badge = textElement('span', `${label}: ${money(value)} RON`, 'transaction-payment-chip');
            modalPayments.append(badge);
        });
        if (!modalPayments.children.length) {
            modalPayments.append(textElement('span', 'Nu exista suma alocata pe metodele afisate.', 'transaction-payment-empty'));
        }

        modalProducts.replaceChildren();
        if (!transaction.products.length) {
            const row = document.createElement('tr');
            const cell = textElement('td', 'Nota fara produse in compnote.');
            cell.colSpan = 4;
            row.append(cell);
            modalProducts.append(row);
        } else {
            transaction.products.forEach(product => {
                const row = document.createElement('tr');
                row.append(
                    textElement('td', product.name),
                    textElement('td', Number(product.quantity || 0).toFixed(2), 'num'),
                    textElement('td', `${money(product.price)} RON`, 'num'),
                    textElement('td', `${money(product.value)} RON`, 'num strong'),
                );
                modalProducts.append(row);
            });
        }

        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('transaction-modal-open');
        modal.querySelector('[data-transaction-close]')?.focus();
    };

    document.querySelectorAll('[data-transaction-close]').forEach(button => button.addEventListener('click', closeModal));
    document.querySelectorAll('.transaction-details-button').forEach(button => button.addEventListener('click', () => openModal(button.dataset.transactionId)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideHover();
            closeModal();
        }
    });
    window.addEventListener('scroll', hideHover, { passive: true });
})();
