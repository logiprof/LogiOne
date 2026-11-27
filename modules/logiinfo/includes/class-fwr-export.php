<?php
/** Exports en bloc (ZIP, CSV) et aller-retour avec l'IA (export anonymisé + import des corrections). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LIR_Export {

	public static function init() {
		add_action( 'admin_post_fwr_import_ia', array( __CLASS__, 'import' ) );
	}

	private static function rows( $ids ) { return array_values( array_filter( array_map( array( 'LIR_Core', 'get' ), $ids ) ) ); }
	private static function tmp( $prefix ) { return wp_tempnam( $prefix ) . '.zip'; }
	private static function clean( $s ) { $s = remove_accents( $s ); $s = preg_replace( '/[^A-Za-z0-9 _.-]+/', '', $s ); return trim( preg_replace( '/\s+/', ' ', $s ) ); }
	private static function send( $path, $name ) {
		if ( defined( 'LIR_TEST' ) ) { rename( $path, LIR_TEST . '/' . $name ); return; }
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		wp_delete_file( $path );
		exit;
	}
	public static function code( $id ) { return 'R' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ); }

	/* ---------------------------------------------------------------- CSV (Excel) */
	private static function csv_string( $rows ) {
		$h = fopen( 'php://temp', 'r+' );
		fwrite( $h, "\xEF\xBB\xBF" );
		fputcsv( $h, array( 'Leçon', 'Exercice', 'Apprenti·e', 'E-mail', 'Version', 'Déposé le', 'Contrôle auto (points)', 'Contrôle auto (max)', 'Contrôle auto (%)', 'Note proposée', 'Note IA', 'Note', 'Statut', 'Décision', 'Motif', 'Vérification de l\'auteur', 'Commentaire', 'Fichiers', 'Auteurs des modifications', 'Alertes de traçabilité' ), ';' );
		foreach ( $rows as $r ) {
			$u = get_userdata( $r['user_id'] );
			$L = LIR_Core::lecon( $r['lecon'] );
			fputcsv( $h, array(
				LIR_Core::code( $r['lecon'] ), $L ? $L['exercice'] : '', LIR_Core::user_label( $r['user_id'] ), $u ? $u->user_email : '', $r['version'],
				mysql2date( 'd.m.Y H:i', $r['created_at'] ), str_replace( '.', ',', (string) (float) $r['auto_score'] ), (float) $r['auto_max'],
				$r['auto_max'] ? round( 100 * $r['auto_score'] / $r['auto_max'] ) : '', LIR_Core::note_proposee( $r['auto_score'], $r['auto_max'] ), (string) $r['ia_note'], $r['note'],
				LIR_Core::STATUTS[ $r['statut'] ] ?? $r['statut'], LIR_Core::DECISIONS[ $r['validation'] ] ?? '', (string) $r['motif'], implode( ' ', LIR_Core::identity( $r )['msgs'] ), (string) $r['commentaire'], implode( ', ', array_column( $r['files'], 'name' ) ),
				implode( ', ', array_keys( LIR_Core::authors( $r ) ) ), implode( ' · ', (array) LIR_Core::trace_flags( $r ) ),
			), ';' );
		}
		rewind( $h );
		$s = stream_get_contents( $h );
		fclose( $h );
		return $s;
	}
	public static function csv( $ids ) {
		$s = self::csv_string( self::rows( $ids ) );
		if ( defined( 'LIR_TEST' ) ) { file_put_contents( LIR_TEST . '/recap.csv', $s ); return; }
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="remises-word_' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $s; // phpcs:ignore
		exit;
	}

	/* ---------------------------------------------------------------- ZIP des fichiers */
	public static function zip( $ids ) {
		$rows = self::rows( $ids );
		$path = self::tmp( 'fwr' );
		$z = new ZipArchive();
		$z->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $rows as $r ) {
			$L = LIR_Core::lecon( $r['lecon'] );
			$dir = sprintf( '%s %s/%s', LIR_Core::code( $r['lecon'] ), self::clean( $L ? $L['titre'] : '' ), self::clean( LIR_Core::user_label( $r['user_id'] ) ) );
			foreach ( $r['files'] as $f ) {
				$p = LIR_Core::abs_path( $f['stored'] );
				if ( file_exists( $p ) ) { $z->addFile( $p, $dir . '/v' . $r['version'] . '_' . mysql2date( 'Y-m-d', $r['created_at'] ) . '_' . self::clean( $f['name'] ) ); }
			}
		}
		$z->addFromString( 'recapitulatif.csv', self::csv_string( $rows ) );
		$z->close();
		self::send( $path, 'remises-word_' . gmdate( 'Y-m-d' ) . '.zip' );
	}

	/* ---------------------------------------------------------------- export pour l'IA */
	/**
	 * Remises à envoyer à l'IA. Par défaut, seules les copies « À corriger » sont gardées : celles déjà corrigées
	 * (par vous ou par l'IA), publiées ou à la corbeille ne sont jamais renvoyées, pour éviter de payer deux fois.
	 */
	public static function ia_ids( $ids, $force = false ) {
		return array_values( array_filter( (array) $ids, function ( $id ) use ( $force ) {
			$r = LIR_Core::get( $id );
			return $r && ( $force ? 'corbeille' !== $r['statut'] : 'depose' === $r['statut'] );
		} ) );
	}
	public static function ia( $ids ) {
		$rows = self::rows( $ids );
		if ( ! $rows ) { return false; }
		$path = self::tmp( 'fwria' );
		$z = new ZipArchive();
		$z->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$manifest = array();
		$lecons = array();
		foreach ( $rows as $r ) {
			$code = self::code( $r['id'] );
			$lecons[ (int) $r['lecon'] ] = true;
			$entry = array( 'code' => $code, 'lecon' => (int) $r['lecon'], 'version' => (int) $r['version'], 'fichiers' => array(),
				'controle_auto' => array( 'points' => (float) $r['auto_score'], 'max' => (float) $r['auto_max'], 'note_proposee' => LIR_Core::note_proposee( $r['auto_score'], $r['auto_max'] ) ) );
			$struct = '';
			foreach ( $r['files'] as $k => $f ) {
				$p = LIR_Core::abs_path( $f['stored'] );
				if ( ! file_exists( $p ) ) { continue; }
				$ext = strtolower( pathinfo( $p, PATHINFO_EXTENSION ) );
				$fn = 'fichier_' . ( $k + 1 ) . '.' . $ext;
				$z->addFile( $p, "copies/$code/$fn" );
				$entry['fichiers'][] = $fn;
				$d = LIR_Core::reader( $p );
				$struct .= "=== $fn ===\n" . ( $d->ok ? $d->summary() : $d->error ) . "\n\n";
			}
			$z->addFromString( "copies/$code/structure.txt", $struct );
			$rep = $r['auto_report'] ? $r['auto_report'] : array();
			$z->addFromString( "copies/$code/controle_auto.md", self::report_md( $rep ) );
			$manifest[] = $entry;
		}
		foreach ( array_keys( $lecons ) as $n ) {
			$L = LIR_Core::lecon( $n );
			$dir = 'lecons/' . LIR_Core::code( $n );
			$md = '# ' . LIR_Core::lecon_label( $n ) . "\n\n## Exercice : {$L['exercice']}\n\n### Mission\n{$L['mission']}\n\n### Consignes (étapes)\n{$L['consignes']}\n";
			// (en-tête commun Word / Excel)
			$g = json_decode( $L['grille'], true );
			if ( $g ) { $md .= "\n### Grille d'évaluation officielle\n"; foreach ( $g as $row ) { $md .= '- ' . wp_strip_all_tags( $row[0] ) . ' : ' . wp_strip_all_tags( $row[1] ) . " pts\n"; } }
			$md .= "\n### Critères du contrôle automatique\n";
			foreach ( LIR_Core::criteria( $n ) as $c ) { $md .= "- {$c[0]} ({$c[1]} pts)\n"; }
			$md .= "\n### Pénalités (aucun point à gagner, seulement des points retirés)\n";
			if ( ! LIR_Core::is_checked( $n ) && LIR_Rules::get( $n ) ) { $md .= '- Texte du fichier de départ perdu ou supprimé par erreur : −' . LIR_Rules::LOSS_PER_PAR . ' pt par paragraphe (au maximum −' . LIR_Rules::LOSS_MAX . "). Garder le texte ne rapporte aucun point : il est toujours fourni.\n"; }
			if ( LIR_Core::expected_names( $n ) ) { $md .= '- Nom du fichier différent de la consigne (' . implode( ' / ', LIR_Core::expected_names( $n ) ) . ') : −' . LIR_Core::NAME_PENALTY . " pt.\n"; }
			$z->addFromString( "$dir/consignes_et_criteres.md", $md );
			if ( LIR_Core::is_checked( $n ) ) {
				$up = wp_upload_dir();
				$pdf = sprintf( '%s/%s/L%3$02d/L%3$02d_resultat_attendu.pdf', $up['basedir'], LIR_Core::folder( $n ), $L['num'] );
				if ( file_exists( $pdf ) ) { $z->addFile( $pdf, "$dir/resultat_attendu.pdf" ); }
				continue;
			}
			$ref = LIR_DIR . sprintf( 'reference/L%02d_resultat_attendu.docx', $n );
			if ( file_exists( $ref ) ) {
				$z->addFile( $ref, "$dir/resultat_attendu.docx" );
				$d = new LIR_Docx( $ref );
				$z->addFromString( "$dir/resultat_attendu_structure.txt", $d->ok ? $d->summary() : '' );
			}
			if ( 14 === $n && file_exists( LIR_DIR . 'reference/L14_lettres_fusionnees.docx' ) ) { $z->addFile( LIR_DIR . 'reference/L14_lettres_fusionnees.docx', "$dir/resultat_attendu_lettres_fusionnees.docx" ); }
		}
		$z->addFromString( 'manifest.json', wp_json_encode( array( 'genere_le' => current_time( 'c' ), 'echelle' => LIR_Core::echelle_label(), 'copies' => $manifest ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		$z->addFromString( 'INSTRUCTIONS_POUR_L_IA.md', self::instructions( count( $manifest ) ) );
		$z->close();
		global $wpdb;
		$now = current_time( 'mysql' );
		foreach ( $rows as $r ) { $wpdb->update( LIR_Core::table(), array( 'ia_export_at' => $now ), array( 'id' => $r['id'] ) ); LIR_Core::log( 'export_ia', $r['id'] ); }
		self::send( $path, 'correction-ia_' . gmdate( 'Y-m-d_His' ) . '.zip' );
	}

	private static function report_md( $rep ) {
		if ( ! $rep || empty( $rep['items'] ) ) { return "Aucun contrôle automatique disponible.\n"; }
		$md = "# Contrôle automatique : {$rep['score']} / {$rep['max']} points\n\n";
		foreach ( $rep['items'] as $it ) {
			if ( ! empty( $it['penalty'] ) ) { $md .= '- ' . ( $it['ok'] ? '[x] ' : '[ ] PÉNALITÉ ' ) . $it['label'] . ( $it['ok'] ? '' : " ({$it['pts']} pt)" ) . "\n"; continue; }
			$md .= ( $it['ok'] ? '- [x] ' : '- [ ] ' ) . $it['label'] . " ({$it['pts']}/{$it['max']})\n";
		}
		if ( ! empty( $rep['errors'] ) ) { $md .= "\nErreurs de lecture : " . implode( ' / ', $rep['errors'] ) . "\n"; }
		return $md;
	}

	public static function instructions( $n ) {
		$e = LIR_Core::echelle_label();
		return <<<MD
# Correction des exercices Word, Excel et PowerPoint – instructions pour l'IA

Tu es formateur·rice en bureautique. Tu corriges **$n copie(s)** d'apprenti·e·s pour les formations LogiProf « Word 365 », « Excel 365 » ou « PowerPoint 365 » (niveau débutant à intermédiaire ; la formation de chaque copie est indiquée dans `lecons/…/consignes_et_criteres.md`). Les copies sont **anonymisées** : chaque copie a un code (R000123).

## Contenu de ce dossier
- `manifest.json` : la liste des copies (code, leçon, fichiers, résultat du contrôle automatique).
- `lecons/Lxx/consignes_et_criteres.md` : la mission, les consignes détaillées et les critères de la leçon.
- `lecons/Lxx/resultat_attendu.docx` et `resultat_attendu_structure.txt` : le corrigé Word et sa structure lue par l'outil (pour Excel et PowerPoint – codes XLxx et PPxx – : `resultat_attendu.pdf`).
- `copies/Rxxxxxx/fichier_1.docx` (ou `.xlsx`, `.pptx`) : la copie de l'apprenti·e (plusieurs fichiers possibles, ex. Word leçon 14 : lettre type + lettres fusionnées).
- `copies/Rxxxxxx/structure.txt` : la structure extraite de la copie (pour PowerPoint : chaque diapositive avec son titre, sa disposition, ses objets, transitions, animations, notes et textes ; pour Word : style de chaque paragraphe, alignement `align` — `both` = justifié —, retraits en cm, espacements avant/après en points, tabulations, listes, mise en forme des caractères, sauts de page… ; seules les valeurs modifiées directement sur le paragraphe sont indiquées, les autres viennent du style).
- `copies/Rxxxxxx/controle_auto.md` : les critères vérifiés automatiquement et les points obtenus.

## Ta mission pour chaque copie
1. Lis les consignes de la leçon, puis compare la copie au résultat attendu. Ouvre le fichier (.docx, .xlsx ou .pptx) si tu le peux. Sinon, appuie-toi sur `structure.txt`.
2. Vérifie en particulier ce que le contrôle automatique ne voit pas bien : texte retapé au lieu d'être déplacé, lignes vides ou espaces utilisés pour la mise en page, ordre et cohérence, rendu général, images ou graphiques mal placés.
3. Le contrôle automatique peut se tromper, par exemple si l'apprenti·e a obtenu le bon résultat par une autre méthode valable. Dans ce cas, corrige-le dans ton évaluation et dis-le dans le commentaire.
4. Propose une **note** (échelle : $e) et un **commentaire bienveillant et précis**, adressé à l'apprenti·e en le vouvoyant : 2 ou 3 points forts, puis les points à améliorer, en citant la commande concernée (par ex. « Accueil › Paragraphe › Interligne »). Reste court : 5 à 8 phrases.

## Format de réponse obligatoire
Réponds avec **un seul fichier JSON** nommé `correction.json`, sans texte autour, au format suivant :

```json
{
  "corrections": [
    {
      "code": "R000123",
      "note": "5.0",
      "commentaire": "Très bon travail sur… Pensez à…",
      "points_forts": ["…"],
      "a_ameliorer": ["…"],
      "ecart_controle_auto": "facultatif : ce que tu as corrigé par rapport au contrôle automatique"
    }
  ]
}
```

Une entrée par copie du manifeste, avec exactement le même `code`. Ce fichier sera réimporté dans WordPress : le formateur relira chaque correction avant de la publier.
MD;
	}

	/* ---------------------------------------------------------------- page « Correction par l'IA » */
	public static function page_ia() {
		echo '<div class="wrap fwr-wrap"><h1>Correction par l\'IA</h1>';
		LIR_Admin::notice();
		echo '<div class="fwr-cols"><div class="fwr-col"><h2>1. Exporter les copies</h2>';
		echo '<p>Dans <a href="' . esc_url( admin_url( 'admin.php?page=fw-remises' ) ) . '">la liste des remises</a>, filtrez (par ex. une leçon ou une classe), puis cliquez sur <strong>🤖 Exporter le filtre pour l\'IA</strong>, ou cochez des remises et choisissez l\'action groupée <em>Exporter pour correction par l\'IA</em>.</p>';
		echo '<div class="notice notice-info inline"><p><strong>Correction en plusieurs fois :</strong> seules les copies au statut <strong>« À corriger »</strong> partent vers l\'IA. Celles qui sont déjà <strong>corrigées</strong> (par vous ou par une importation précédente de l\'IA), <strong>publiées</strong> ou à la corbeille sont automatiquement laissées de côté : vous pouvez exporter, importer, puis exporter à nouveau plus tard sans repayer les mêmes copies. Une copie déjà exportée mais pas encore importée porte la mention <em>📤 envoyée à l\'IA</em> dans la liste.</p><p>Pour refaire corriger une copie précise, cochez-la et choisissez l\'action <em>Exporter pour l\'IA, même déjà corrigée</em>.</p></div>';
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=fwr_bulk&op=export_ia&tout=1&statut=depose' ), 'fwr_bulk' );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">🤖 Exporter toutes les remises « À corriger »</a></p>';
		echo '<p class="description">Le ZIP est <strong>anonymisé</strong> : les copies portent un code (R000123) et ne contiennent ni nom ni e-mail. Il comprend les consignes, les critères, le corrigé et des instructions prêtes à l\'emploi.</p>';
		echo '<h2>2. Faire corriger</h2><ol><li>Ouvrez Claude (claude.ai) et joignez le ZIP.</li><li>Écrivez : <em>« Corrige ces copies en suivant le fichier INSTRUCTIONS_POUR_L_IA.md et renvoie-moi correction.json. »</em></li><li>Téléchargez le fichier <code>correction.json</code> produit.</li></ol>';
		echo '</div><div class="fwr-col"><h2>3. Importer les corrections</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fwr_import_ia">';
		wp_nonce_field( 'fwr_import_ia' );
		echo '<p><input type="file" name="fichier" accept=".json,application/json" required></p>';
		echo '<p><label><input type="checkbox" name="preremplir" value="1" checked> Pré-remplir ma note et mon commentaire avec la proposition de l\'IA (si je n\'ai encore rien saisi)</label></p>';
		echo '<p><label><input type="checkbox" name="ecraser" value="1"> … et remplacer aussi ce que j\'ai déjà saisi</label></p>';
		echo '<p><button class="button button-primary">Importer correction.json</button></p></form>';
		echo '<p class="description">Les observations de l\'IA sont toujours conservées à part : elles s\'affichent dans la fiche de correction, à côté de la comparaison entre la copie et le résultat attendu. Les copies concernées passent au statut <strong>« Corrigé (non publié) »</strong> : relisez-les, puis publiez-les une à une ou avec l\'action groupée <em>Publier les retours</em>.</p></div></div></div>';
	}

	public static function import() {
		check_admin_referer( 'fwr_import_ia' );
		if ( ! current_user_can( LIR_CAP ) ) { wp_die( 'Accès refusé.' ); }
		$back = admin_url( 'admin.php?page=fw-remises-ia' );
		$tmp = $_FILES['fichier']['tmp_name'] ?? ''; // phpcs:ignore
		$raw = $tmp ? file_get_contents( $tmp ) : ''; // phpcs:ignore
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $raw );
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m ) ) { $raw = $m[1]; }
		$data = json_decode( $raw, true );
		$list = isset( $data['corrections'] ) ? $data['corrections'] : ( is_array( $data ) && isset( $data[0]['code'] ) ? $data : null );
		if ( ! $list ) { wp_safe_redirect( add_query_arg( 'fwr_msg', rawurlencode( 'Fichier non reconnu : il faut un JSON contenant une liste « corrections ».' ), $back ) ); exit; }
		$ok = 0; $filled = 0; $unknown = array();
		$ecraser = ! empty( $_POST['ecraser'] );
		$prefill = ! empty( $_POST['preremplir'] ) || $ecraser;
		global $wpdb;
		foreach ( $list as $c ) {
			$code = isset( $c['code'] ) ? strtoupper( trim( $c['code'] ) ) : '';
			$id = (int) ltrim( substr( $code, 1 ), '0' );
			$r = $id ? LIR_Core::get( $id ) : null;
			if ( $r && ! LI_Formateurs::can_see( $r ) ) { $r = null; } // seulement ses propres remises
			if ( ! $r ) { $unknown[] = $code; continue; }
			$clean = function ( $v ) { return is_array( $v ) ? array_values( array_filter( array_map( function ( $x ) { return sanitize_text_field( (string) $x ); }, $v ) ) ) : array(); };
			$details = array( 'points_forts' => $clean( $c['points_forts'] ?? array() ), 'a_ameliorer' => $clean( $c['a_ameliorer'] ?? array() ), 'ecart_controle_auto' => sanitize_textarea_field( (string) ( $c['ecart_controle_auto'] ?? '' ) ) );
			$data = array( 'ia_note' => sanitize_text_field( (string) ( $c['note'] ?? '' ) ), 'ia_commentaire' => sanitize_textarea_field( (string) ( $c['commentaire'] ?? '' ) ), 'ia_details' => wp_json_encode( $details ), 'ia_at' => current_time( 'mysql' ) );
			if ( $prefill && ( $ecraser || ( '' === $r['note'] && ! $r['commentaire'] ) ) ) {
				$r2 = array_merge( $r, array( 'ia_commentaire' => $data['ia_commentaire'], 'ia_details' => $details ) );
				$data += array( 'note' => $data['ia_note'], 'commentaire' => LIR_Admin::ia_texte( $r2 ), 'corrige_par' => get_current_user_id(), 'corrige_at' => current_time( 'mysql' ) );
				if ( 'publie' !== $r['statut'] ) { $data['statut'] = 'corrige'; }
				$filled++;
			}
			$wpdb->update( LIR_Core::table(), $data, array( 'id' => $id ) );
			LIR_Core::log( 'import_ia', $id, isset( $data['statut'] ) ? $data['statut'] : '' );
			$ok++;
		}
		$msg = "$ok observation(s) de l'IA importée(s), $filled correction(s) pré-remplie(s)" . ( $unknown ? ', codes inconnus : ' . implode( ', ', array_slice( $unknown, 0, 10 ) ) : '' ) . '.';
		wp_safe_redirect( add_query_arg( 'fwr_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=fw-remises' ) ) );
		exit;
	}
}
