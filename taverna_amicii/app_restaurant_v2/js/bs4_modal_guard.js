/*! bs4_modal_guard.js - safe helpers for Bootstrap 4 modals */
(function(){
  function hoistModals(){
    try {
      document.querySelectorAll('.modal').forEach(function(m){
        if (m.parentElement !== document.body) document.body.appendChild(m);
      });
    } catch(e){}
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hoistModals);
  } else { hoistModals(); }

  if (window.jQuery) {
    jQuery(document).on('hide.bs.modal hidden.bs.modal', function(e){
      var act = document.activeElement;
      if (act && e.target.contains && e.target.contains(act)) {
        try { act.blur(); } catch(_){}
      }
    });
    jQuery(document).on('shown.bs.modal', function(e){
      var $m = jQuery(e.target);
      var $af = $m.find('[data-autofocus], input[type=password], input, textarea, button').filter(':visible:first');
      if ($af.length) { try { $af.focus().select(); } catch(_){} }
    });
  }
})();
