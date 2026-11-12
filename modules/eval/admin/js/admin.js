/* Réponse AJAX admin : recharge si OK, sinon affiche le message du serveur (avant : rechargement muet). */
window.logievalAfter = function (r) {
    if (r && r.success) { location.reload(); return; }
    var msg = (r && r.data && (r.data.message || (typeof r.data === 'string' ? r.data : ''))) || 'L’opération a échoué.';
    alert(msg);
};
jQuery(document).ajaxError(function (e, xhr, settings) {
    if (settings && settings.url && settings.url.indexOf('admin-ajax.php') !== -1 && String(settings.data || '').indexOf('logieval_admin') !== -1) {
        alert('Connexion au serveur impossible ou session expirée. Rechargez la page et réessayez.');
    }
});
jQuery(function ($) {

    /* ── Route teacher-load AJAX (for evaluation form) ───────── */
    // (no-op line removed: it referenced the global "wp" object, which is not always defined)

    // Handle get_classe_teachers via standard admin AJAX
    // (registered in PHP dynamically)
    $(document).on('change', '#eval-classe', function () {
        loadClasseTeachers($(this).val());
    });

});
