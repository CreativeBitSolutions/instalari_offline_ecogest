/*! compat_diag.js - quick console diagnostics for jQuery & Bootstrap 4 */
(function(){
  try {
    var infos = {
      jquery_version: (window.jQuery && jQuery.fn && jQuery.fn.jquery) || null,
      bootstrap_modal_plugin: !!(window.jQuery && jQuery.fn && jQuery.fn.modal),
      popper_present: !!(window.Popper),
      bootstrap_js_present: !!(window.jQuery && jQuery.fn && jQuery.fn.modal && typeof jQuery.fn.modal === 'function'),
    };
    if (window.console && console.info) {
      console.info('[compat_diag]', infos);
    }
  } catch(e){}
})();
