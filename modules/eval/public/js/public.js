/**
 * LogiEval — public.js
 * Vote-based evaluation: Oui / Non / Abstention per sub-criterion
 */
jQuery(function($){
    'use strict';
    // Nonce issued with the page that was just rendered (it is tied to the logged-in teacher).
    if (window.logievalNonce && window.logievalPublic) logievalPublic.nonce = window.logievalNonce;
    $(document).on('click', '.logieval-clickable-card', function(e){
        if ($(e.target).closest('a, button, input, textarea, select, form').length) return;
        var href = $(this).data('href');
        if (href) window.location.href = href;
    });

    // Pleine largeur sans barre de défilement horizontale : largeur visible réelle (sans la barre verticale).
    function setViewportWidth(){ document.documentElement.style.setProperty('--lge-vw', document.documentElement.clientWidth + 'px'); }
    setViewportWidth();
    $(window).on('resize', setViewportWidth);

    /* Envoi au serveur (LogiOne 1.28) : si la page est restée ouverte longtemps, le jeton de sécurité est
       renouvelé automatiquement et la demande refaite ; si la session a expiré, un message clair s'affiche. */
    function send(data) {
        var d = $.Deferred();
        var attempt = function (retry) {
            data.nonce = logievalPublic.nonce;
            $.post(logievalPublic.ajax_url, data).done(function (r) {
                if (r && !r.success && r.data && r.data.code === 'nonce_expired' && r.data.nonce && retry) {
                    logievalPublic.nonce = r.data.nonce;
                    attempt(false);
                    return;
                }
                if (r && !r.success && r.data && r.data.code === 'session_expired') { sessionExpired(); }
                d.resolve(r);
            }).fail(function (x, st, e) { d.reject(x, st, e); });
        };
        attempt(true);
        return d.promise();
    }
    window.logievalSend = send;
    function sessionExpired() {
        if ($('#lge-session-expired').length) return;
        $('<div id="lge-session-expired" class="logieval-error-banner is-sticky"><span>⚠ Votre session a expiré : vos dernières réponses n’ont pas pu être enregistrées.</span> <a href="' + window.location.href.replace(/"/g, '') + '">Recharger la page et saisir mon identifiant</a></div>').prependTo('.logieval-app .lge-body').first();
    }

    if (!window.logievalEvalId) return;

    var evalId   = window.logievalEvalId;
    var totalMax = window.logievalTotalMax;
    var testMode = !!window.logievalTestMode;
    var commentTimer = {};
    var pendingComments = {};

    /* ── Status helpers ───────────────────────────────────────── */
    function setStatus(msg, cls) {
        $('#logieval-save-status').text(msg).removeClass('saving error').addClass(cls||'');
        var $m = $('.lge-mobile-status');
        if (!$m.length) $m = $('<div class="lge-mobile-status" role="status" aria-live="polite"></div>').appendTo('body');
        $m.text(msg).removeClass('saving error').addClass(cls||'');
    }
    function showError(msg, sticky) {
        $('#logieval-error-banner').remove();
        var $b = $('<div id="logieval-error-banner" class="logieval-error-banner">'
            +'<span>⚠ </span><span>'+escHtml(msg)+'</span>'
            +' <button onclick="jQuery(this).parent().remove()" style="margin-left:12px;background:none;border:none;cursor:pointer">✕</button>'
            +'</div>');
        var $target = $('.logieval-autosave-bar');
        if ($target.length) $target.after($b);
        else $('.logieval-eval-wrap .lge-body').prepend($b);
        // Une réponse non enregistrée reste signalée jusqu'à ce que l'enseignant ferme le message.
        if (!sticky) setTimeout(function(){ $b.fadeOut(400, function(){ $(this).remove(); }); }, 12000);
    }
    function escHtml(s){ return $('<div>').text(s).html(); }
    function nowHM(){ var d=new Date(); return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); }

    /* ── Vote button click ────────────────────────────────────── */
    var voteQueues = {};
    function groupState($groups) {
        return $groups.map(function(){ var $a = $(this).find('.logieval-vote-btn.active'); return $a.length ? $a.index() : -1; }).get();
    }
    function restoreGroups($groups, state, type) {
        $groups.each(function(i){
            var $b = $(this).find('.logieval-vote-btn').removeClass('active');
            if (type === 'radio') $b.text('Choisir');
            if (state[i] >= 0) { var $on = $b.eq(state[i]).addClass('active'); if (type === 'radio') $on.text('✓ Choisi'); }
        });
    }
    window.logievalVote = function(btn, vote) {
        var $btn   = $(btn);
        var $group = $btn.closest('.logieval-vote-btns');
        var eleveId = $group.data('eleve');
        var scId    = $group.data('sc');
        var objId   = $group.data('obj');
        var type    = $group.data('type');
        var $scope  = type === 'radio' ? $('.logieval-vote-btns[data-eleve="'+eleveId+'"][data-obj="'+objId+'"]') : $group;
        var before  = groupState($scope);
        // Cliquer sur la réponse déjà choisie l'efface (le critère redevient « non renseigné »).
        if ($btn.hasClass('active')) vote = 'clear';

        if (type === 'radio') {
            $scope.find('.logieval-vote-btn').removeClass('active').text('Choisir');
            if (vote !== 'clear') $btn.addClass('active').text('✓ Choisi');
        } else {
            $group.find('.logieval-vote-btn').removeClass('active');
            if (vote !== 'clear') $btn.addClass('active');
        }
        updateProgress();
        if (testMode) {
            setStatus('Entraînement : rien n’est enregistré');
            return;
        }
        setStatus('Enregistrement…','saving');
        // Une seule demande à la fois par élève et objectif, dans l'ordre des clics.
        var key = eleveId + '_' + objId;
        var run = function () {
            return send({
                action:     'logieval_public',
                sub_action: 'save_vote',
                eval_id:    evalId,
                eleve_id:   eleveId,
                sc_id:      scId,
                vote:       vote
            })
            .done(function(r){
                if (r.success) {
                    $scope.removeClass('lge-unsaved');
                    setStatus((r.data && r.data.cleared ? '✓ Réponse effacée à ' : '✓ Enregistré à ')+(r.data&&r.data.saved_at?r.data.saved_at:nowHM()));
                    setTimeout(function(){ refreshTallies(eleveId); }, 500);
                } else {
                    restoreGroups($scope, before, type); updateProgress();
                    $scope.addClass('lge-unsaved');
                    showError(((r.data&&r.data.message)?r.data.message:'Erreur de sauvegarde.') + ' Votre réponse n’a PAS été enregistrée.', true);
                    setStatus('⚠ Réponse non enregistrée','error');
                }
            })
            .fail(function(){
                restoreGroups($scope, before, type); updateProgress();
                $scope.addClass('lge-unsaved');
                showError('Pas de connexion : votre réponse n’a PAS été enregistrée. Vérifiez le réseau puis cliquez à nouveau.', true);
                setStatus('⚠ Réponse non enregistrée','error');
            });
        };
        voteQueues[key] = (voteQueues[key] || $.Deferred().resolve().promise()).then(run, run);
    };

    /* ── Arbitrage (maître on tie) ────────────────────────────── */
    window.logievalArbitrage = function(btn, eleveId, scId, decision) {
        var $btn = $(btn);
        $btn.prop('disabled',true).text('⏳');
        send({
            action:     'logieval_public',
            nonce:      logievalPublic.nonce,
            sub_action: 'save_arbitrage',
            eval_id:    evalId,
            eleve_id:   eleveId,
            sc_id:      scId,
            decision:   decision
        })
        .done(function(r){
            if (r.success) {
                var $wrapper = $btn.closest('.logieval-sc-wrapper');
                $wrapper.removeClass('logieval-sc-need-arb logieval-sc-tie');
                var label = decision ? '⚖ Tranché : ✓ Accordé' : '⚖ Tranché : ✗ Refusé';
                $btn.closest('.logieval-arb-btns').replaceWith(
                    '<span class="logieval-tally-tie">'+label+'</span>'
                );
                // Recheck overall tie status for student header
                checkStudentTies(eleveId);
            } else {
                showError((r.data&&r.data.message)||'Erreur arbitrage.');
                $btn.prop('disabled',false).text(decision?'✓ Accorder':'✗ Refuser');
            }
        })
        .fail(function(){ showError('Erreur réseau.'); $btn.prop('disabled',false); });
    };

    /* ── Comment auto-save ────────────────────────────────────── */
    function saveComment($ta) {
        var eleveId = $ta.data('eleve');
        var scId    = $ta.data('sc');
        var key     = eleveId+'_'+scId;
        if (testMode) {
            delete pendingComments[key];
            setStatus('Mode test : commentaire non enregistré');
            return $.Deferred().resolve({success:true}).promise();
        }
        return send({
            action:      'logieval_public',
            nonce:       logievalPublic.nonce,
            sub_action:  'save_comment',
            eval_id:     evalId,
            eleve_id:    eleveId,
            sc_id:       scId,
            commentaire: $ta.val()
        });
    }

    function flushComment($ta){
        var key = $ta.data('eleve')+'_'+$ta.data('sc');
        clearTimeout(commentTimer[key]); delete commentTimer[key];
        if (!pendingComments[key]) return;
        var sent = $ta.val();
        saveComment($ta)
            .done(function(r){
                if (r.success) {
                    // Gardé en attente si le texte a encore changé pendant l'envoi.
                    if ($ta.val() === sent) delete pendingComments[key];
                    setStatus(testMode ? 'Entraînement : rien n’est enregistré' : '✓ Commentaire enregistré à '+nowHM());
                } else {
                    showError(((r.data&&r.data.message)||'Erreur commentaire.') + ' Le commentaire n’a pas été enregistré : il sera renvoyé à la prochaine modification.', true);
                    setStatus('⚠ Commentaire non enregistré','error');
                }
            })
            .fail(function(){
                showError('Pas de connexion : le commentaire n’a pas été enregistré. Il sera renvoyé dès que vous le modifierez ou quitterez le champ.', true);
                setStatus('⚠ Commentaire non enregistré','error');
            });
    }
    function autoSize(el){ el.style.height='auto'; el.style.height=el.scrollHeight+'px'; }

    $(document).on('input', '.logieval-comment-input', function(){
        var $ta = $(this);
        var key = $ta.data('eleve')+'_'+$ta.data('sc');
        pendingComments[key] = $ta;
        autoSize(this);
        setStatus('Enregistrement…','saving');
        clearTimeout(commentTimer[key]);
        commentTimer[key] = setTimeout(function(){ flushComment($ta); }, 1000);
    });
    $(document).on('blur', '.logieval-comment-input', function(){ flushComment($(this)); });
    $('.logieval-comment-input').each(function(){ if (this.value) autoSize(this); });

    // « + Commentaire » ouvre le champ
    $(document).on('click', '.lge-add-comment', function(){
        var $ta = $(this).siblings('.logieval-comment-input').removeClass('logieval-hidden');
        $(this).remove();
        $ta.trigger('focus');
    });

    /* ── Progression : par élève et pour toute la classe ──────── */
    function studentState($card){
        var $groups = $card.find('.logieval-vote-btns');
        if (!$groups.length) return null;
        var total = 0, done = 0, first = null, radioSeen = {};
        $groups.each(function(){
            var $g = $(this);
            if ($g.closest('.logieval-sc-list-ne').length) return;
            if ($g.data('type') === 'radio') {
                var o = $g.data('obj');
                if (radioSeen[o]) return;
                radioSeen[o] = true; total++;
                var $all = $card.find('.logieval-vote-btns[data-obj="'+o+'"]');
                if ($all.find('.logieval-vote-btn.active').length) done++;
                else if (!first) first = $all.first();
            } else {
                total++;
                if ($g.find('.logieval-vote-btn.active').length) done++;
                else if (!first) first = $g;
            }
        });
        return {total: total, done: done, first: first};
    }

    function updateProgress(){
        var students = 0, complete = 0, all = 0, allDone = 0;
        $('.logieval-student-card').each(function(){
            var $card = $(this), st = studentState($card);
            var $p = $('#prog-'+$card.data('eleve'));
            if (!st) { $p.prop('hidden', true); return; }
            students++; all += st.total; allDone += st.done;
            var ok = st.done >= st.total;
            if (ok) complete++;
            $p.prop('hidden', false).toggleClass('is-complete', ok)
              .text(ok ? '✓ Complet' : st.done+' / '+st.total+' critères');
            $card.toggleClass('lge-complete', ok);
        });
        if (!students) { $('.lge-overall-wrap, #lge-goto-missing').hide(); return; }
        $('#lge-overall').text(complete+' élève'+(complete>1?'s':'')+' complet'+(complete>1?'s':'')+' sur '+students);
        $('#lge-overall-fill').css('width', (all ? Math.round(allDone/all*100) : 0)+'%');
        $('#lge-goto-missing').toggle(allDone < all);
    }

    function firstMissing(){
        var found = null;
        $('.logieval-student-card').each(function(){
            var st = studentState($(this));
            if (st && st.first) { found = {eleve: $(this).data('eleve'), $g: st.first}; return false; }
        });
        return found;
    }

    function goToMissing(){
        var m = firstMissing();
        if (!m) return false;
        if ($('#body-'+m.eleve).hasClass('logieval-hidden')) window.toggleStudent(m.eleve, true);
        var $w = m.$g.closest('.logieval-sc-wrapper');
        setTimeout(function(){
            var top = $w.offset().top - 140;
            $('html,body').animate({scrollTop: top}, 250);
            $w.addClass('lge-flash'); setTimeout(function(){ $w.removeClass('lge-flash'); }, 1600);
        }, 60);
        return true;
    }
    $('#lge-goto-missing').on('click', function(e){ e.preventDefault(); goToMissing(); });

    /* ── Élève suivant ────────────────────────────────────────── */
    $(document).on('click', '.lge-next', function(){ window.toggleStudent($(this).data('next')); });

    /* ── « Tout Oui » : remplit les critères Oui/Non pas encore renseignés ── */
    $(document).on('click', '.lge-fill', function(){
        var $btn = $(this), eleveId = $btn.data('eleve'), objId = $btn.data('obj') || 0;
        var $scope = objId ? $('#sc-list-'+eleveId+'-'+objId) : $('#body-'+eleveId);
        var $targets = $scope.find('.logieval-vote-btns[data-type="checkbox"]').filter(function(){
            return !$(this).closest('.logieval-sc-list-ne').length && !$(this).find('.logieval-vote-btn.active').length;
        });
        if (!$targets.length) { setStatus('Tous ces critères sont déjà renseignés'); return; }
        function apply(scIds){
            $targets.each(function(){
                var $g = $(this);
                if (scIds && scIds.indexOf(parseInt($g.data('sc'),10)) === -1) return;
                $g.find('.logieval-vote-oui').addClass('active');
            });
            updateProgress();
        }
        if (testMode) { apply(null); setStatus('Entraînement : rien n’est enregistré'); return; }
        var label = $btn.text();
        $btn.prop('disabled', true).text('Enregistrement…');
        setStatus('Enregistrement…','saving');
        send({
            action: 'logieval_public', nonce: logievalPublic.nonce, sub_action: 'fill_yes',
            eval_id: evalId, eleve_id: eleveId, obj_id: objId
        })
        .done(function(r){
            if (r.success) {
                apply((r.data && r.data.sc_ids) || []);
                setStatus('✓ Enregistré à '+((r.data && r.data.saved_at) || nowHM()));
                setTimeout(function(){ refreshTallies(eleveId); }, 500);
            } else { showError((r.data&&r.data.message)||'Erreur.'); setStatus('⚠ Erreur','error'); }
        })
        .fail(function(){ showError('Erreur réseau.'); setStatus('⚠ Erreur','error'); })
        .always(function(){ $btn.prop('disabled', false).text(label); });
    });

    $('#btn-update-eval-settings').on('click', function(e){
        e.preventDefault();
        var ensIds = [];
        $('.front-eval-ens-check:checked').each(function(){ ensIds.push($(this).val()); });
        if (!ensIds.length) {
            showError('Sélectionnez au moins un enseignant.');
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Sauvegarde…');
        send({
            action: 'logieval_public',
            nonce: logievalPublic.nonce,
            sub_action: 'update_eval_settings',
            eval_id: evalId,
            date_echeance: $('#front-eval-echeance').val(),
            enseignant_ids: ensIds
        })
        .done(function(r){
            if (r.success) {
                setStatus('✓ Évaluation modifiée');
                location.reload();
            } else {
                showError((r.data&&r.data.message)||'Erreur.');
                $btn.prop('disabled', false).text('Enregistrer les modifications');
            }
        })
        .fail(function(){
            showError('Erreur réseau.');
            $btn.prop('disabled', false).text('Enregistrer les modifications');
        });
    });

    $('#btn-send-class-report').on('click', function(e){
        e.preventDefault();
        if (!confirm('Envoyer le rapport détaillé de classe par e-mail aux enseignants attribués ?')) return;
        var $btn = $(this).prop('disabled', true).text('Envoi…');
        send({
            action: 'logieval_public',
            nonce: logievalPublic.nonce,
            sub_action: 'send_class_report',
            eval_id: evalId
        })
        .done(function(r){
            if (r.success) {
                var sent = r.data && r.data.sent ? r.data.sent : 0;
                $btn.text('Rapport envoyé');
                setStatus('✓ Rapport envoyé à '+sent+' enseignant(s)');
            } else {
                showError((r.data&&r.data.message)||'Erreur pendant l’envoi.');
                $btn.prop('disabled', false).text('Envoyer le rapport par e-mail');
            }
        })
        .fail(function(){
            showError('Erreur réseau pendant l’envoi.');
            $btn.prop('disabled', false).text('Envoyer le rapport par e-mail');
        });
    });

    $('#btn-send-pending-reminder').on('click', function(e){
        e.preventDefault();
        if (!confirm('Envoyer un rappel par e-mail aux enseignants qui n’ont pas encore validé leur évaluation ?')) return;
        var $btn = $(this).prop('disabled', true).text('Envoi…');
        send({
            action: 'logieval_public',
            nonce: logievalPublic.nonce,
            sub_action: 'send_pending_reminder',
            eval_id: evalId
        })
        .done(function(r){
            if (r.success) {
                var sent = r.data && r.data.sent ? r.data.sent : 0;
                setStatus('✓ Rappel envoyé à '+sent+' enseignant(s)');
                $btn.prop('disabled', false).text('Relancer les retardataires');
            } else {
                showError((r.data&&r.data.message)||'Erreur pendant l’envoi.');
                $btn.prop('disabled', false).text('Relancer les retardataires');
            }
        })
        .fail(function(){
            showError('Erreur réseau pendant l’envoi.');
            $btn.prop('disabled', false).text('Relancer les retardataires');
        });
    });

    /* ── Refresh tally for one student ───────────────────────── */
    function refreshTallies(eleveId) {
        if ($('.logieval-eval-wrap').data('canPeer') !== 1) return;
        send({
            action:     'logieval_public',
            nonce:      logievalPublic.nonce,
            sub_action: 'get_others_responses',
            eval_id:    evalId
        })
        .done(function(r){
            if (!r.success||!r.data) return;
            r.data.forEach(function(item){
                if (eleveId !== -1 && item.eleve_id != eleveId) return;
                var eleveId_ = item.eleve_id;
                var $tally = $('.logieval-vote-btns[data-eleve="'+eleveId_+'"][data-sc="'+item.sc_id+'"]')
                              .closest('.logieval-sc-wrapper').find('.logieval-tally');
                if ($tally.length) {
                    var nouiHtml = '<span class="logieval-tally-oui" title="'+item.nb_oui+' vote(s) Oui">✓ '+item.nb_oui+'</span>';
                    var nnonHtml = '<span class="logieval-tally-non" title="'+item.nb_non+' vote(s) Non">✗ '+item.nb_non+'</span>';
                    var nabsHtml = item.nb_abs > 0 ? '<span class="logieval-tally-abs" title="'+item.nb_abs+' abstention(s)">⊘ '+item.nb_abs+'</span>' : '';
                    $tally.html(nouiHtml+nnonHtml+nabsHtml);
                }
                var $others = $('.logieval-others[data-eleve="'+eleveId_+'"][data-sc="'+item.sc_id+'"]');
                if ($others.length) {
                    var isRadio = $('.logieval-vote-btns[data-eleve="'+eleveId_+'"][data-sc="'+item.sc_id+'"]').data('type') === 'radio';
                    var ouiLabel = isRadio ? 'Choisi par' : 'Oui';
                    var html = '';
                    if (item.oui_teachers && item.oui_teachers.length) {
                        html += '<div class="logieval-others-line logieval-others-oui"><strong>'+ouiLabel+' :</strong> '+escHtml(item.oui_teachers.join(', '))+'</div>';
                    }
                    if (item.non_teachers && item.non_teachers.length) {
                        html += '<div class="logieval-others-line logieval-others-non"><strong>Non :</strong> '+escHtml(item.non_teachers.join(', '))+'</div>';
                    }
                    if (item.abs_teachers && item.abs_teachers.length) {
                        html += '<div class="logieval-others-line logieval-others-abs"><strong>Abstention :</strong> '+escHtml(item.abs_teachers.join(', '))+'</div>';
                    }
                    $others.html(html);
                }
            });
        });
    }

    /* ── Check if student still has ties ─────────────────────── */
    function checkStudentTies(eleveId) {
        var hasTie = $('.logieval-sc-need-arb').filter(function(){
            return $(this).closest('[data-eleve="'+eleveId+'"]').length > 0;
        }).length > 0;
        var $alert = $('#tie-alert-'+eleveId);
        if (!hasTie) $alert.remove();
    }

    /* ── Mark done ────────────────────────────────────────────── */
    $('#btn-mark-done').on('click', function(){
        var missing = 0;
        $('.logieval-student-card').each(function(){ var st = studentState($(this)); if (st) missing += st.total - st.done; });
        if (missing > 0) {
            showError('Il reste '+missing+' critère'+(missing>1?'s':'')+' à renseigner. Voici le premier.');
            goToMissing();
            return;
        }
        if (!confirm('Marquer votre évaluation comme terminée ? Vous pourrez encore la corriger tant que l’évaluation n’est pas clôturée.')) return;
        var $btn = $(this).prop('disabled',true).text('Finalisation…');
        send({
            action:'logieval_public', nonce:logievalPublic.nonce,
            sub_action:'mark_done', eval_id:evalId
        })
        .done(function(r){
            if (r.success) {
                if (r.data&&r.data.auto_closed) alert('Tous les enseignants ont terminé : l’évaluation est clôturée.');
                else alert('Merci, votre évaluation est terminée. '+(r.data&&r.data.pending?r.data.pending+' enseignant(s) doivent encore terminer.':''));
                location.reload();
            } else {
                showError((r.data&&r.data.message)||'Erreur.'); $btn.prop('disabled',false).text('Marquer terminé');
            }
        })
        .fail(function(){ showError('Erreur réseau.'); $btn.prop('disabled',false).text('Marquer terminé'); });
    });

    $('#btn-mark-undone').on('click', function(){
        if (!confirm('Rouvrir vos réponses pour les corriger ? Pensez à cliquer à nouveau sur « Marquer terminé » ensuite.')) return;
        send({action:'logieval_public',nonce:logievalPublic.nonce,sub_action:'mark_undone',eval_id:evalId})
        .done(function(r){ if(r.success) location.reload(); else showError((r.data&&r.data.message)||'Erreur.'); })
        .fail(function(){ showError('Pas de connexion : réessayez.'); });
    });

    $(document).on('click', '.logieval-master-mark-undone', function(e){
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('Réouvrir la saisie de cet enseignant pour lui permettre de corriger son évaluation ?')) return;
        var $btn = $(this).prop('disabled', true).text('…');
        send({
            action: 'logieval_public',
            nonce: logievalPublic.nonce,
            sub_action: 'master_mark_teacher_undone',
            eval_id: $btn.data('eval') || evalId,
            teacher_id: $btn.data('teacher')
        })
        .done(function(r){
            if (r.success) location.reload();
            else {
                showError((r.data&&r.data.message)||'Erreur.');
                $btn.prop('disabled', false).text('Réouvrir');
            }
        })
        .fail(function(){
            showError('Erreur réseau.');
            $btn.prop('disabled', false).text('Réouvrir');
        });
    });

    $('#btn-force-close-finished').on('click', function(){
        if (!confirm('Clôturer cette évaluation uniquement avec les enseignants qui ont terminé ? Les enseignants en cours ne seront pas pris en compte dans les résultats.')) return;
        var $btn = $(this).prop('disabled',true).text('⏳ Clôture…');
        send({
            action:'logieval_public', nonce:logievalPublic.nonce,
            sub_action:'force_close_finished', eval_id:evalId
        })
        .done(function(r){
            if (r.success) {
                alert('Évaluation clôturée avec les évaluations terminées.');
                location.reload();
            } else {
                showError((r.data&&r.data.message)||'Erreur.');
                $btn.prop('disabled',false).text('Clôturer maintenant');
            }
        })
        .fail(function(){
            showError('Erreur réseau.');
            $btn.prop('disabled',false).text('Clôturer maintenant');
        });
    });

    $('#btn-reopen-evaluation').on('click', function(){
        if (!confirm('Réouvrir cette évaluation clôturée ? Les enseignants pourront corriger leurs saisies après réouverture.')) return;
        var $btn = $(this).prop('disabled',true).text('⏳ Réouverture…');
        send({
            action:'logieval_public', nonce:logievalPublic.nonce,
            sub_action:'reopen_evaluation', eval_id:evalId
        })
        .done(function(r){
            if (r.success) {
                alert('Évaluation réouverte.');
                location.reload();
            } else {
                showError((r.data&&r.data.message)||'Erreur.');
                $btn.prop('disabled',false).text('Réouvrir');
            }
        })
        .fail(function(){
            showError('Erreur réseau.');
            $btn.prop('disabled',false).text('Réouvrir');
        });
    });

    /* ── Export Word ──────────────────────────────────────────── */
    $('#btn-export-word').on('click', function(e){
        e.preventDefault();
        var f=$('<form>',{method:'POST',action:logievalPublic.ajax_url})
            .append($('<input>',{type:'hidden',name:'action',value:'logieval_export_front'}))
            .append($('<input>',{type:'hidden',name:'nonce',value:logievalPublic.nonce}))
            .append($('<input>',{type:'hidden',name:'eval_id',value:evalId}));
        $('body').append(f); f.submit(); f.remove();
    });

    /* ── Non-evaluable toggle ─────────────────────────────────── */
    window.logievalToggleNE = function(btn) {
        var $btn   = $(btn);
        var eleveId = $btn.data('eleve');
        var objId   = $btn.data('obj');
        var isNE    = parseInt($btn.data('ne'))===1;
        var newNE   = isNE?0:1;
        function applyNEState() {
            var $scList = $('#sc-list-'+eleveId+'-'+objId);
            var $header = $btn.closest('.logieval-obj-header');
            var $score  = $('#obj-score-'+eleveId+'-'+objId);
            if (newNE) {
                $btn.data('ne',1).text('↩ Rétablir').addClass('logieval-btn-ne-active');
                // Les réponses déjà données restent (mises de côté) : « Rétablir » les fait revenir.
                $header.addClass('logieval-obj-ne');
                $scList.addClass('logieval-sc-list-ne');
                if ($score.length) {
                    $score.addClass('logieval-obj-score-ne').html('<span class="logieval-ne-label">Non évaluable</span>');
                }
            } else {
                $btn.data('ne',0).text('⊘ Non évaluable').removeClass('logieval-btn-ne-active');
                $header.removeClass('logieval-obj-ne');
                $scList.removeClass('logieval-sc-list-ne');
                if ($score.length) {
                    $score.removeClass('logieval-obj-score-ne').html('<span>—</span> <small>(recalculé au rechargement)</small>');
                }
            }
        }
        if (testMode) {
            applyNEState(); updateProgress();
            setStatus('Entraînement : rien n’est enregistré');
            return;
        }
        $btn.prop('disabled',true).text('⏳');
        send({
            action:'logieval_public',nonce:logievalPublic.nonce,sub_action:'toggle_ne',
            eval_id:evalId,eleve_id:eleveId,obj_id:objId,active:newNE
        })
        .done(function(r){
            if (r.success) {
                applyNEState(); updateProgress();
            } else { showError((r.data&&r.data.message)||'Erreur.'); $btn.text(isNE ? '↩ Rétablir' : '⊘ Non évaluable'); }
            $btn.prop('disabled',false);
        })
        .fail(function(){ showError('Pas de connexion : rien n’a été modifié.'); $btn.prop('disabled',false).text(isNE ? '↩ Rétablir' : '⊘ Non évaluable'); });
    };

    $(document).on('click', function(e){
        $('.lge-menu[open]').each(function(){ if (!$(e.target).closest(this).length) this.removeAttribute('open'); });
    });

    updateProgress();

    /* ── Refresh colleagues' answers every 30s ────────────────── */
    setTimeout(function(){
        // Initial refresh for tallies
        refreshTallies(-1);
    }, 2000);
    setInterval(function(){ refreshTallies(-1); }, 30000);

    /* ── Warn on unload if status saving ─────────────────────── */
    $(window).on('beforeunload', function(){
        if ($('#logieval-save-status').hasClass('saving') || Object.keys(pendingComments).length)
            return 'Sauvegarde en cours…';
    });

});
