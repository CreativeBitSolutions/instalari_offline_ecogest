<?php
$wooPopupClientId = (int)($_SESSION['client_id'] ?? 0);

if (in_array($wooPopupClientId, [25, 26], true)):
    $wooPopupContext = isset($wooPopupContext) && $wooPopupContext === 'login' ? 'login' : 'sales';
    $wooPopupActionUrl = $wooPopupContext === 'login' ? '#' : 'vanzare_importa_comanda_woo.php';
    $wooPopupActionLabel = $wooPopupContext === 'login' ? 'Autentifică-te pentru preluare' : 'Deschide comenzile';
?>
<aside id="wooOrderPopup"
       class="woo-order-popup"
       role="alert"
       aria-live="assertive"
       aria-atomic="true"
       aria-hidden="true">
    <div class="woo-order-popup__stripe" aria-hidden="true"></div>
    <button type="button" class="woo-order-popup__close" aria-label="Închide notificarea">&times;</button>

    <div class="woo-order-popup__body">
        <div class="woo-order-popup__signal" aria-hidden="true">
            <span>!</span>
        </div>

        <div class="woo-order-popup__content">
            <div class="woo-order-popup__eyebrow">GLOVO GRAND PLAZA · COMANDĂ ONLINE</div>
            <div class="woo-order-popup__headline">
                <strong class="woo-order-popup__count">1</strong>
                <span class="woo-order-popup__title">comandă nouă</span>
            </div>
            <p class="woo-order-popup__message">O comandă de pe site așteaptă să fie preluată.</p>
            <div class="woo-order-popup__ids" hidden></div>
        </div>
    </div>

    <a class="woo-order-popup__action" href="<?= htmlspecialchars($wooPopupActionUrl, ENT_QUOTES, 'UTF-8') ?>">
        <span><?= htmlspecialchars($wooPopupActionLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span aria-hidden="true">→</span>
    </a>
</aside>

<style>
    .woo-order-popup {
        --woo-alert: #ff3b30;
        --woo-warning: #ffbf2f;
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 10850;
        width: min(430px, calc(100vw - 32px));
        overflow: hidden;
        color: #f8fafc;
        background: #141a22;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 18px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .48), 0 0 0 5px rgba(255, 59, 48, .13);
        font-family: Bahnschrift, "Segoe UI", sans-serif;
        opacity: 0;
        visibility: hidden;
        transform: translate3d(0, 24px, 0) scale(.97);
        pointer-events: none;
        transition: opacity .22s ease, transform .22s ease, visibility .22s ease;
    }

    .woo-order-popup.is-visible {
        opacity: 1;
        visibility: visible;
        transform: translate3d(0, 0, 0) scale(1);
        pointer-events: auto;
    }

    .woo-order-popup__stripe {
        height: 9px;
        background: repeating-linear-gradient(
            -45deg,
            var(--woo-warning) 0 12px,
            #111820 12px 24px
        );
    }

    .woo-order-popup__close {
        position: absolute;
        top: 18px;
        right: 14px;
        z-index: 2;
        width: 40px;
        height: 40px;
        padding: 0;
        color: #dbe2ea;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 12px;
        font: 700 28px/36px Bahnschrift, "Segoe UI", sans-serif;
        cursor: pointer;
    }

    .woo-order-popup__close:hover,
    .woo-order-popup__close:focus {
        color: #fff;
        background: rgba(255, 255, 255, .16);
        outline: 3px solid rgba(255, 191, 47, .35);
    }

    .woo-order-popup__body {
        display: flex;
        gap: 16px;
        align-items: flex-start;
        padding: 22px 62px 18px 22px;
    }

    .woo-order-popup__signal {
        position: relative;
        flex: 0 0 58px;
        display: grid;
        place-items: center;
        width: 58px;
        height: 58px;
        color: #fff;
        background: var(--woo-alert);
        border-radius: 50%;
        box-shadow: 0 0 0 8px rgba(255, 59, 48, .14);
        font: 900 34px/1 Bahnschrift, "Segoe UI", sans-serif;
    }

    .woo-order-popup.is-visible .woo-order-popup__signal::after {
        content: "";
        position: absolute;
        inset: -8px;
        border: 2px solid rgba(255, 59, 48, .65);
        border-radius: 50%;
        animation: wooOrderSignal 1.4s ease-out infinite;
    }

    .woo-order-popup__content {
        min-width: 0;
    }

    .woo-order-popup__eyebrow {
        margin-bottom: 5px;
        color: var(--woo-warning);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
    }

    .woo-order-popup__headline {
        display: flex;
        align-items: baseline;
        gap: 9px;
        line-height: 1;
    }

    .woo-order-popup__count {
        color: #fff;
        font-size: 42px;
        font-weight: 900;
        letter-spacing: -.04em;
    }

    .woo-order-popup__title {
        color: #fff;
        font-size: 23px;
        font-weight: 800;
    }

    .woo-order-popup__message {
        margin: 9px 0 0;
        color: #bdc8d5;
        font-size: 15px;
        line-height: 1.35;
    }

    .woo-order-popup__ids {
        margin-top: 8px;
        color: #eef2f7;
        font-size: 13px;
        font-weight: 700;
    }

    .woo-order-popup__action {
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: 58px;
        padding: 14px 22px;
        color: #121820 !important;
        background: var(--woo-warning);
        font-size: 16px;
        font-weight: 900;
        text-decoration: none !important;
        transition: background .15s ease, padding .15s ease;
    }

    .woo-order-popup__action:hover,
    .woo-order-popup__action:focus {
        color: #121820 !important;
        background: #ffd064;
        padding-left: 26px;
        padding-right: 18px;
        outline: none;
    }

    @keyframes wooOrderSignal {
        from { opacity: .9; transform: scale(.82); }
        to { opacity: 0; transform: scale(1.35); }
    }

    @media (max-width: 575.98px) {
        .woo-order-popup {
            right: 12px;
            bottom: 12px;
            width: calc(100vw - 24px);
        }

        .woo-order-popup__body {
            gap: 13px;
            padding: 19px 54px 16px 17px;
        }

        .woo-order-popup__signal {
            flex-basis: 48px;
            width: 48px;
            height: 48px;
            font-size: 29px;
        }

        .woo-order-popup__count { font-size: 36px; }
        .woo-order-popup__title { font-size: 20px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .woo-order-popup,
        .woo-order-popup__action { transition: none; }
        .woo-order-popup.is-visible .woo-order-popup__signal::after { animation: none; }
    }
</style>

<script>
(function () {
    var popup = document.getElementById('wooOrderPopup');
    if (!popup) return;

    var countEl = popup.querySelector('.woo-order-popup__count');
    var titleEl = popup.querySelector('.woo-order-popup__title');
    var messageEl = popup.querySelector('.woo-order-popup__message');
    var idsEl = popup.querySelector('.woo-order-popup__ids');
    var closeEl = popup.querySelector('.woo-order-popup__close');
    var actionEl = popup.querySelector('.woo-order-popup__action');
    var context = <?= json_encode($wooPopupContext) ?>;
    var currentSignature = '';
    var dismissedSignature = '';

    function cleanIds(ids) {
        return Array.isArray(ids) ? ids.map(function (id) {
            return String(parseInt(id, 10) || '');
        }).filter(Boolean) : [];
    }

    function hide() {
        popup.classList.remove('is-visible');
        popup.setAttribute('aria-hidden', 'true');
    }

    function show() {
        popup.classList.add('is-visible');
        popup.setAttribute('aria-hidden', 'false');
    }

    function update(count, ids) {
        count = Math.max(0, parseInt(count, 10) || 0);
        ids = cleanIds(ids);

        if (count === 0) {
            currentSignature = '';
            dismissedSignature = '';
            hide();
            return;
        }

        currentSignature = ids.length ? ids.join(',') : 'count:' + count;
        countEl.textContent = String(count);
        titleEl.textContent = count === 1 ? 'comandă nouă' : 'comenzi noi';
        messageEl.textContent = count === 1
            ? 'O comandă de pe site așteaptă să fie preluată.'
            : 'Sunt ' + count + ' comenzi de pe site care așteaptă să fie preluate.';

        if (ids.length) {
            var visibleIds = ids.slice(0, 4).map(function (id) { return '#' + id; });
            idsEl.textContent = 'Comenzi: ' + visibleIds.join(', ') + (ids.length > 4 ? '…' : '');
            idsEl.hidden = false;
        } else {
            idsEl.textContent = '';
            idsEl.hidden = true;
        }

        if (dismissedSignature !== currentSignature) {
            show();
        }
    }

    closeEl.addEventListener('click', function () {
        dismissedSignature = currentSignature;
        hide();
    });

    if (context === 'login') {
        actionEl.addEventListener('click', function (event) {
            event.preventDefault();
            dismissedSignature = currentSignature;
            hide();

            var firstUser = document.querySelector('.my_button:not([disabled])');
            if (firstUser) {
                firstUser.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstUser.focus();
            }
        });
    }

    window.agecsWooOrderPopup = {
        update: update,
        hide: hide
    };
})();
</script>
<?php
endif;

unset($wooPopupClientId, $wooPopupContext, $wooPopupActionUrl, $wooPopupActionLabel);
?>
