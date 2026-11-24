#!/usr/bin/env python3
"""Vérifie un fichier d'import LogiProjet (logiprojet-travail, version 1).

Usage : python3 verifier.py activite.json
Sortie : la liste des erreurs (à corriger) et des avertissements (à relire), puis un résumé.
Code de retour 0 si aucune erreur.
"""
import json
import re
import sys

LISTES = ["mission", "actions", "plan", "faire", "eviter", "etapes", "rendu", "jourj", "checklist"]
TITRES = ["mission", "contenu", "plan", "page2", "regles", "etapes", "rendu", "jourj", "checklist", "grille"]
FORMATS = ["pptx", "pdf", "docx", "xlsx", "odp", "key", "img", "mp4", "zip"]
REPERES = {"{module}", "{total}", "{n}", "{lecons}", "{periodes}", "{date_debut}", "{date_fin}", "{k}"}
DATE = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$")


def main(path):
    err, warn = [], []
    try:
        a = json.load(open(path, encoding="utf-8"))
    except Exception as e:  # noqa: BLE001
        print(f"ERREUR : le fichier n'est pas un JSON valable ({e}).")
        return 1

    def need(cond, msg):
        if not cond:
            err.append(msg)

    need(a.get("format") == "logiprojet-travail", "« format » doit valoir « logiprojet-travail ».")
    need(a.get("version") == 1, "« version » doit valoir 1.")
    need(isinstance(a.get("title"), str) and a["title"].strip(), "« title » est obligatoire.")
    graded = a.get("graded", True)
    need(isinstance(graded, bool), "« graded » doit valoir true ou false.")
    size = a.get("group_size", 2)
    need(isinstance(size, int) and 1 <= size <= 6, "« group_size » doit être un entier de 1 à 6.")
    fmts = a.get("formats", ["pptx"])
    need(isinstance(fmts, list) and fmts and all(f in FORMATS for f in fmts), f"« formats » : liste parmi {FORMATS}.")
    for k in ("due_at", "date_debut"):
        v = a.get(k)
        need(v is None or (isinstance(v, str) and DATE.match(v)), f"« {k} » : null ou « AAAA-MM-JJTHH:MM » (heure suisse).")

    lessons = a.get("lessons", [])
    need(isinstance(lessons, list), "« lessons » doit être une liste.")
    lesson_ids = set()
    for i, l in enumerate(lessons if isinstance(lessons, list) else []):
        need(isinstance(l, dict) and isinstance(l.get("title"), str) and l["title"].strip(), f"lessons[{i}] : « title » obligatoire.")
        if isinstance(l, dict) and l.get("id"):
            lesson_ids.add(l["id"])
        if isinstance(l, dict) and l.get("title", "").startswith(("M1", "M2", "M3", "M4", "M5", "M6", "M7")):
            warn.append(f"lessons[{i}] : retirez le préfixe du module du titre (« {l['title']} »).")
    course = a.get("course")
    need(course is None or (isinstance(course, dict) and course.get("title")), "« course » : null ou un objet avec au moins « title ».")

    grille = a.get("grille", [])
    need(isinstance(grille, list), "« grille » doit être une liste.")
    ids = set()
    for i, c in enumerate(grille if isinstance(grille, list) else []):
        if not isinstance(c, dict):
            err.append(f"grille[{i}] doit être un objet.")
            continue
        need(isinstance(c.get("id"), str) and re.match(r"^[a-z0-9_]+$", c["id"] or ""), f"grille[{i}] : « id » en minuscules (ex. c1).")
        need(c.get("id") not in ids, f"grille[{i}] : l'id « {c.get('id')} » est en double.")
        ids.add(c.get("id"))
        need(isinstance(c.get("bloc"), str) and c["bloc"].strip(), f"grille[{i}] : « bloc » obligatoire (ex. « A · Contenu »).")
        need(isinstance(c.get("label"), str) and c["label"].strip(), f"grille[{i}] : « label » obligatoire.")
        if c.get("lesson_id") and lesson_ids and c["lesson_id"] not in lesson_ids:
            warn.append(f"grille[{i}] : lesson_id {c['lesson_id']} n'est pas dans « lessons ».")
    if graded and not grille:
        err.append("Une activité notée (graded: true) a besoin d'une grille.")
    if len(grille) > 40:
        err.append("La grille compte plus de 40 critères (limite de LogiProjet).")

    c = a.get("consigne")
    need(isinstance(c, dict), "« consigne » doit être un objet (voir references/format-logiprojet.md).")
    if isinstance(c, dict):
        need(isinstance(c.get("note", ""), str), "consigne.note doit être un texte.")
        for k in LISTES:
            v = c.get(k, [])
            need(isinstance(v, list) and all(isinstance(x, str) for x in v), f"consigne.{k} doit être une liste de textes.")
            if isinstance(v, list) and len(v) > 20:
                err.append(f"consigne.{k} : 20 lignes au maximum.")
            for x in v if isinstance(v, list) else []:
                if len(x) > 600:
                    err.append(f"consigne.{k} : une ligne dépasse 600 caractères.")
                if x.count("**") % 2:
                    err.append(f"consigne.{k} : « ** » non fermé dans « {x[:60]}… ».")
                for rep in re.findall(r"\{[a-z_]+\}", x):
                    if rep not in REPERES:
                        warn.append(f"consigne.{k} : repère inconnu {rep}.")
        for k in ("actions", "plan"):
            for x in c.get(k, []) if isinstance(c.get(k), list) else []:
                if "|" not in x:
                    warn.append(f"consigne.{k} : « Titre | texte » attendu dans « {x[:60]} ».")
        for k in (c.get("titres") or {}):
            need(k in TITRES, f"consigne.titres : clé inconnue « {k} » (clés : {TITRES}).")
        if not c.get("etapes"):
            warn.append("consigne.etapes est vide : la durée en périodes ne s'affichera pas.")
        if "{date_fin}" not in json.dumps(c, ensure_ascii=False):
            warn.append("La date de fin ({date_fin}) n'apparaît nulle part dans la consigne.")
        mots = json.dumps(c, ensure_ascii=False).lower()
        if size != 2 and " duo" in mots:
            warn.append(f"group_size vaut {size}, mais la consigne parle de « duo ».")
        if not graded and ("note sur" in mots or "compte pour votre moyenne" in mots):
            warn.append("Activité non notée, mais la consigne parle de note.")

    for e in err:
        print("ERREUR :", e)
    for w in warn:
        print("À RELIRE :", w)
    total = 2 * len(grille) if isinstance(grille, list) else 0
    print(f"\n{a.get('title')} · {'noté sur 6 (' + str(total) + ' points)' if graded else 'non noté'} · groupe de {size} · formats {fmts} · "
          f"{len(lessons) if isinstance(lessons, list) else 0} leçon(s) · {len(grille) if isinstance(grille, list) else 0} critère(s) · "
          f"{len((c or {}).get('etapes', []) or [])} période(s)")
    print("OK : prêt à importer dans LogiProjet." if not err else f"{len(err)} erreur(s) à corriger.")
    return 0 if not err else 1


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(sys.argv[1]))
