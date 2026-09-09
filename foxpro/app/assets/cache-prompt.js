(function () {
    const modal = document.querySelector('[data-cache-prompt]');
    if (!modal) {
        return;
    }

    const closeButton = modal.querySelector('[data-cache-prompt-close]');
    const rebuildButton = modal.querySelector('[data-cache-prompt-rebuild]');
    const message = modal.querySelector('[data-cache-prompt-message]');
    let busy = false;

    const show = (text) => {
        if (text && message) {
            message.textContent = text;
        }
        modal.hidden = false;
    };

    const hide = () => {
        modal.hidden = true;
    };

    const checkStatus = async () => {
        try {
            const response = await fetch('cache_status.php', {cache: 'no-store'});
            const data = await response.json();
            if (data.needs_refresh) {
                show('Cache-ul nu este incarcat sau nu mai este actual. Apasa butonul pentru reincarcare.');
            }
        } catch (error) {
            show('Statusul cache-ului nu poate fi verificat. Poti incerca reincarcarea manual.');
        }
    };

    closeButton?.addEventListener('click', hide);
    modal.querySelector('[data-cache-prompt-backdrop]')?.addEventListener('click', hide);

    rebuildButton?.addEventListener('click', async () => {
        if (busy) {
            return;
        }

        busy = true;
        rebuildButton.disabled = true;
        rebuildButton.textContent = 'Se reincarca...';
        try {
            const response = await fetch('cache_rebuild.php', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Reincarcarea cache-ului a esuat.');
            }
            hide();
            window.dispatchEvent(new CustomEvent('cache-rebuilt', {detail: data}));
        } catch (error) {
            show(error.message || 'Reincarcarea cache-ului a esuat.');
        } finally {
            busy = false;
            rebuildButton.disabled = false;
            rebuildButton.textContent = 'Reincarca cache';
        }
    });

    window.setInterval(checkStatus, 60000);
})();
