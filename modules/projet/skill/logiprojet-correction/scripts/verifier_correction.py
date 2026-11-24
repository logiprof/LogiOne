#!/usr/bin/env python3
"""Vérifie une correction LogiProjet avant de la rendre à l'enseignant.

Usage : python3 verifier_correction.py EXPORT(.zip ou correction.json) CORRECTION.json

Contrôle que la correction correspond à l'export : chaque dépôt présent une fois,
avec sa ref et son numéro ; chaque critère de la grille noté 0, 1 ou 2 avec une
justification ; 2 points forts, une piste et un résumé ; aucun nom d'apprenti.
Code de sortie 0 si tout est en ordre.
"""
import json
import re
import sys
import zipfile


def charger_export(chemin):
    if chemin.lower().endswith('.zip'):
        with zipfile.ZipFile(chemin) as z:
            nom = next((n for n in z.namelist() if n.endswith('correction.json')), None)
            if not nom:
                sys.exit('ERREUR : correction.json introuvable dans ' + chemin)
            return json.loads(z.read(nom).decode('utf-8'))
    with open(chemin, encoding='utf-8') as f:
        return json.load(f)


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    exp = charger_export(sys.argv[1])
    with open(sys.argv[2], encoding='utf-8') as f:
        try:
            cor = json.load(f)
        except json.JSONDecodeError as e:
            sys.exit('ERREUR : le fichier de correction n’est pas un JSON valable (' + str(e) + ')')

    err, warn = [], []
    if exp.get('format') != 'logiprojet-correction-export':
        err.append('Le premier fichier n’est pas un export de correction LogiProjet.')
    t = exp.get('travail', {})
    grille = [c['id'] for c in t.get('grille', [])]
    notee = t.get('notee', True)
    attendus = {d['ref']: d for d in exp.get('depots', [])}

    if cor.get('format') != 'logiprojet-correction':
        err.append('format doit valoir "logiprojet-correction".')
    if cor.get('version') != 1:
        err.append('version doit valoir 1.')
    if cor.get('travail_id') != t.get('id'):
        err.append('travail_id doit valoir %s (celui de l’export).' % t.get('id'))
    if not str(cor.get('modele', '')).strip() or '(nom' in str(cor.get('modele', '')):
        warn.append('modele : indiquez le modèle Claude utilisé.')

    vus = set()
    points = []
    for i, d in enumerate(cor.get('depots', []), 1):
        ref = d.get('ref', '#%d' % i)
        if ref not in attendus:
            err.append('%s : ref inconnue dans l’export.' % ref)
            continue
        if ref in vus:
            err.append('%s : présent deux fois.' % ref)
        vus.add(ref)
        if d.get('depot') != attendus[ref]['depot']:
            err.append('%s : depot doit valoir %s.' % (ref, attendus[ref]['depot']))
        crit = {str(c.get('id')): c for c in d.get('criteres', [])}
        inconnus = set(crit) - set(grille)
        if inconnus:
            err.append('%s : critères inconnus %s.' % (ref, ', '.join(sorted(inconnus))))
        total = 0
        for cid in grille:
            c = crit.get(cid)
            if c is None:
                err.append('%s : critère %s manquant.' % (ref, cid))
                continue
            if c.get('score') not in (0, 1, 2):
                err.append('%s/%s : score doit valoir 0, 1 ou 2.' % (ref, cid))
            else:
                total += c['score']
            if len(str(c.get('justification', '')).strip()) < 8:
                err.append('%s/%s : justification manquante.' % (ref, cid))
        points.append((ref, total))
        pf = d.get('points_forts', [])
        if not isinstance(pf, list) or len([p for p in pf if str(p).strip()]) < 2:
            err.append('%s : 2 points forts attendus.' % ref)
        if len(str(d.get('piste', '')).strip()) < 10:
            err.append('%s : piste d’amélioration manquante.' % ref)
        if len(str(d.get('resume', '')).strip()) < 10:
            warn.append('%s : résumé pour l’enseignant manquant.' % ref)
        for a in d.get('a_revoir', []):
            if a not in grille:
                err.append('%s : a_revoir contient %s, absent de la grille.' % (ref, a))
        if not isinstance(d.get('copie_suspecte', False), bool):
            err.append('%s : copie_suspecte doit valoir true ou false.' % ref)
        texte = ' '.join([str(d.get('piste', ''))] + [str(p) for p in pf] + [str(c.get('justification', '')) for c in crit.values()])
        if re.search(r'\b(tu|ton|ta|tes|toi)\b', texte, re.I):
            warn.append('%s : les retours doivent vouvoyer les apprentis.' % ref)
        if not notee and re.search(r'\b[1-6](?:[.,]5)?\s*/\s*6\b|\bnote\b', ' '.join([str(d.get('piste', ''))] + [str(p) for p in pf]), re.I):
            warn.append('%s : activité non notée, n’écrivez pas de note dans le retour.' % ref)

    for ref in attendus:
        if ref not in vus:
            if attendus[ref].get('extrait') or attendus[ref].get('fichier'):
                err.append('%s : dépôt de l’export non corrigé.' % ref)
            else:
                warn.append('%s : pas de contenu dans l’export, laissé à l’enseignant.' % ref)

    tot = 2 * len(grille)
    print('%s · %s · %d dépôt(s) corrigé(s) sur %d · grille de %d critère(s), %d points' % (
        t.get('titre', '?'), 'noté' if notee else 'non noté', len(vus), len(attendus), len(grille), tot))
    if notee and tot:
        for ref, p in points:
            n = round((p / tot * 5 + 1) * 2) / 2
            print('  %s : %d / %d points → note indicative %.1f' % (ref, p, tot, n))
    for w in warn:
        print('À RELIRE : ' + w)
    for e in err:
        print('ERREUR : ' + e)
    if err:
        sys.exit(1)
    print('OK : prêt à importer dans LogiProjet.')


if __name__ == '__main__':
    main()
